<?php
require_once 'Customizing/global/plugins/Services/Cron/CronHook/ReportMaster/classes/ReportBase/class.ilObjReportBase.php';
require_once 'Services/GEV/Utils/classes/class.gevOrgUnitUtils.php';
ini_set("memory_limit","2048M"); 
ini_set('max_execution_time', 0);
set_time_limit(0);

class ilObjReportTrainerOpTrainerOrgu extends ilObjReportBase {
	const MIN_ROW = "0";
	const shift = '<div class = "inline_block">&nbsp;&nbsp;</div>';
	protected $categories;
	protected $relevant_parameters = array();	


	public function __construct($ref_id = 0) {
		parent::__construct($ref_id);
		global $tree;
		$this->tree = $tree;
	}

	public function initType() {
		 $this->setType("xoto");
	}

	protected function createLocalReportSettings() {
		$this->local_report_settings =
			$this->s_f->reportSettings('rep_robj_toto');
	}

	protected function getRowTemplateTitle() {
		return "tpl.gev_trainer_operation_by_orgu_and_trainer_row.html";
	}

	protected function buildTable($table) {
		$table->column("title", $this->plugin->txt("title"), true, "", false, false);
		foreach($this->meta_categories as $meta_category => $categories) {
			$table->column($meta_category."_d", $this->meta_category_names[$meta_category], true, "", false, false);
			$table->column($meta_category."_h", "Std.", true, "", false, false);
		}
		return parent::buildTable($table);
	}

	public function prepareReport() {
		include_once $this->plugin->getDirectory()."/config/cfg.trainer_operation_by_trainer_and_orgu.php";
		$this->meta_categories = $meta_categories;
		$this->meta_category_names = $meta_category_names;
		$this->top_nodes = $top_nodes;
		parent::prepareReport();
	}

	protected function buildFilter($filter) {
		$filter ->multiselect(	"tutor_name"
								 , $this->plugin->txt("crs_tutor")
								 , "hu.user_id"
								 , $this->getTEPTutors()
								 , array()
								 , ""
								 , 300
								 , 160
								 )
				->multiselect( 	"org_unit"
								 , $this->plugin->txt("org_unit_short")
								 , "ht.orgu_title"
								 , $this->getOrgusForFilter($this->top_nodes)
								 , array()
								 , " OR TRUE "
								 , 300
								 , 160
								 )
				->dateperiod( 	"period"
								 , $this->plugin->txt("period")
								 , $this->plugin->txt("until")
								 , "ht.begin_date"
								 , "ht.begin_date"
								 , date("Y")."-01-01"
								 , date("Y")."-12-31"
								 , false
								 , " OR ht.hist_historic IS NULL"
								 )
				->static_condition("hu.hist_historic = 0")
				->static_condition("ht.hist_historic = 0")
				->static_condition("(ht.category != 'Training' OR (ht.context_id != 0 AND ht.context_id IS NOT NULL))")
				->static_condition("ht.deleted = 0")
				->static_condition("ht.user_id != 0")
				->static_condition("ht.orgu_title != '-empty-'")
				->static_condition("ht.row_id > ".self::MIN_ROW)
				->action($this->filter_action)
				->compile();
		return $filter;
	}

	protected function buildOrder($order) {
		return $order;
	}

	protected function buildQuery($query) {
		$query 	->select("ht.orgu_title")
				->select_raw("CONCAT(hu.lastname,', ',hu.firstname) as title");
		foreach($this->meta_categories as $meta_category => $categories) {
			$query	->select_raw($this->daysPerTEPMetaCategory($categories, $meta_category."_d"))
					->select_raw($this->hoursPerTEPMetaCategory($categories, $meta_category."_h"));
		}
		$query	->select("hu.user_id")
				->from("hist_tep ht")
				->join("hist_user hu")
					->on(" ht.user_id = hu.user_id")
				->join("hist_tep_individ_days htid")
					->on("ht.individual_days = htid.id")
				->group_by("ht.orgu_title")
				->group_by("ht.user_id")
				->compile();
		return $query;
	}

	protected function fetchData() {
		$query = $this->buildQueryStatement();
		$this->pre_data = array();
			//	die($query);
		$res = $this->gIldb->query($query);

		while ($rec = $this->gIldb->fetchAssoc($res)) {
			$this->pre_data[$rec["orgu_title"]][] = $rec;
		}
		$this->orgu_filter = $this->filter->get("org_unit");
		if($this->orgu_filter) {
			$top_nodes = array();

			foreach ($this->orgu_filter as $orgu_title) {
				$obj_id = ilObject::_getIdsForTitle($orgu_title, 'orgu')[0];
			
				if($obj_id !== null) {
					$top_nodes[] = gevObjectUtils::getRefId($obj_id);
				}
			}
		} else {
			$top_nodes = $this->top_nodes;
		}

		$top_sup_orgus = $this->getTopSuperiorNodesOfUser($top_nodes);
		$tree_data = array();
		foreach ($top_sup_orgus as $orgu) {
			$tree_data[] = $this->buildReportTree($orgu);
		}
		foreach($tree_data as $branch) {
			$this->fillData($branch);
		}
		return $this->report_data;
	}

	protected function daysPerTEPMetaCategory($categories, $name) {
		$sql = "SUM(IF(".$this->gIldb->in('category',$categories,false,"text")." ,1,0)) as ".$name;
		return $sql;
	}

	protected function hoursPerTEPMetaCategory($categories, $name) {
		$sql =
			"SUM(IF(".$this->gIldb->in('category',$categories,false,"text")." ,"
			."	IF(htid.end_time IS NOT NULL AND htid.start_time IS NOT NULL,"
			."			LEAST(CEIL( TIME_TO_SEC( TIMEDIFF( htid.end_time, htid.start_time ) )* htid.weight /720000) *2,8),"
			."			LEAST(CEIL( 28800* htid.weight /720000) *2,8))"
			."	,0)) as ".$name;
		return $sql;
	}

	protected function arrayAddMetaCategories(array $factor1, array $factor2) {
		foreach($this->data_fields as $data_field) {
			$factor1[$meta_category] += $factor2[$meta_category];
		}
	}

	protected function getOrgusForFilter($below_orgus = null) {
		$all_sup_orgus = $this->user_utils->getOrgUnitsWhereUserIsSuperior();
		$all_sup_orgus_ref = array();

		foreach ($all_sup_orgus as $orgu) {
			$all_sup_orgus_ref[] = $orgu["ref_id"];
		}
		$all_sup_orgus_ref = array_unique($all_sup_orgus_ref);

		if($below_orgus !== null) {
			$childs = gevOrgUnitUtils::getAllChildren($below_orgus);
			foreach ($childs as &$orgu) {
				$orgu = $orgu["ref_id"];
			}
			$below_orgus = array_unique(array_merge($childs,$below_orgus));
			$all_sup_orgus_ref = array_intersect($all_sup_orgus_ref,$below_orgus);
		}

		foreach ($all_sup_orgus_ref as &$orgu) {
			$orgu =  gevOrgUnitUtils::getTitleByRefId($orgu);
		}
		asort($all_sup_orgus_ref);
		return $all_sup_orgus_ref;
	}

	protected function getTopSuperiorNodesOfUser($below_orgus = null) {
		$all_sup_orgus = $this->user_utils->getOrgUnitsWhereUserIsSuperior();
		$all_sup_orgus_ref = array();

		foreach ($all_sup_orgus as $orgu) {
			$all_sup_orgus_ref[] = $orgu["ref_id"];
		}
		$all_sup_orgus_ref = array_unique($all_sup_orgus_ref);

		if($below_orgus !== null) {
			$childs = gevOrgUnitUtils::getAllChildren($below_orgus);
			foreach ($childs as &$orgu) {
				$orgu = $orgu["ref_id"];
			}
			$below_orgus = array_unique(array_merge($childs,$below_orgus));
			$all_sup_orgus_ref = array_intersect($all_sup_orgus_ref,$below_orgus);
		}

		$sql = 	"SELECT obj_id, t1.child as ref_id, t2.child FROM tree t1 "
				."	JOIN object_reference ore "
				."		ON ore.ref_id = t1.child "
				."	LEFT JOIN tree t2 "
				." 		ON t1.lft > t2.lft AND t1.rgt < t2.rgt "
				."		AND ".$this->gIldb->in("t2.child",$all_sup_orgus_ref,false,"text")
				." WHERE ".$this->gIldb->in("t1.child",$all_sup_orgus_ref,false,"text")
				."		AND ore.deleted IS NULL "
				." HAVING t2.child IS NULL";

		$top_sup_orgus = array();
		$res = $this->gIldb->query($sql);

		while($rec = $this->gIldb->fetchAssoc($res)) {
			$top_sup_orgus[] = $rec["ref_id"];
		}
		return $top_sup_orgus;
	}

	protected function buildReportTree($ref_id,$offset = "") {
		$title =  gevOrgUnitUtils::getTitleByRefId($ref_id);
		$children = $this->tree->getChildsByType($ref_id,'orgu');
		$return = array("title"=>$title,"trainers"=>$this->pre_data[$title],"children"=>array());

		foreach ($return["trainers"] as &$trainers) {
			$trainers["title"] = $offset.self::shift.'<div class = "inline_block">'.$trainers["title"].'</div>';
		}

		asort($return["trainers"]);

		foreach($children as $child) {
			$return["children"][] = $this->buildReportTree($child["ref_id"],$offset.self::shift);
		}

		$return["sum"] = $this->sumMetaCategories($return["trainers"]);

		foreach ($return["children"] as $child_nr => $child) {
			$return["sum"] = $this->sumMetaCategories(array($return["sum"],$child["sum"]));
		}
		$return["sum"]["title"] = $offset.'<div class = "inline_block"><b>'.$return["title"].'</b></div>';
		return $return;
	}

	protected function  fillData($data_level) {
		$this->report_data[] = $data_level["sum"];
		foreach ($data_level["trainers"] as $values) {
			$this->report_data[] = $values;
		}
		foreach ($data_level["children"] as $child) {
			$this->fillData($child);
		}
	}

	protected function sumMetaCategories ($arrays) {
		$return = array();
		foreach ($this->meta_categories as $meta_category => $categories) {
			$auxh = 0;
			$auxd = 0;
			foreach ($arrays as $array) {
				$auxh += $array[$meta_category."_h"];
				$auxd += $array[$meta_category."_d"];
			}
			$return[$meta_category."_h"] = $auxh;
			$return[$meta_category."_d"] = $auxd;
		}
		return $return;
	}

	public function getRelevantParameters() {
		return $this->relevant_parameters;
	}

	protected function createTemplateFile() {
		$str = fopen($this->plugin->getDirectory()."/templates/default/"
			."tpl.gev_trainer_operation_by_orgu_and_trainer_row.html","w");
		$tpl = '<tr class="{CSS_ROW}"><td></td>'."\n".'<td class = "bordered_right" style= "white-space:nowrap">{VAL_TITLE}</td>';
		foreach($this->meta_categories as $meta_category => $categories) {
			$tpl .= "\n".'<td align = "right">{VAL_'.strtoupper($meta_category).'_D}</td>';
			$tpl .= "\n".'<td align = "right" class = "bordered_right">{VAL_'.strtoupper($meta_category).'_H}</td>';
			$i++;
		}
		$tpl .= "\n</tr>";
		fwrite($str,$tpl);
		fclose($str);
	}

	protected function getTEPTutors() {
		$sql = 	"SELECT hu.user_id ,CONCAT(hu.lastname,', ',hu.firstname) as fullname FROM hist_tep ht \n"
				."	JOIN hist_user hu ON hu.user_id = ht.user_id \n"
				."	WHERE ht.hist_historic = 0 AND hu.hist_historic = 0"
				."		AND ht.row_id > ".$this->gIldb->quote(self::MIN_ROW)
				."	GROUP BY ht.user_id";
		$res = $this->gIldb->query($sql);
		$return = array();
		while($rec = $this->gIldb->fetchAssoc($res)) {
			$return[$rec["user_id"]] = $rec["fullname"];
		}
		asort($return);
		return $return;
	}
}