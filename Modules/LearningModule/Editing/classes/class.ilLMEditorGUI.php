<?php

/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */


/**
 * GUI class for learning module editor
 *
 * @author Alex Killing <alex.killing@gmx.de>
 *
 * @ilCtrl_Calls ilLMEditorGUI: ilObjLearningModuleGUI
 *
 * @ingroup ModulesIliasLearningModule
 */
class ilLMEditorGUI implements ilCtrlBaseClassInterface
{
    /**
     * @var ilCtrl
     */
    protected $ctrl;

    /**
     * @var ilRbacSystem
     */
    protected $rbacsystem;

    /**
     * @var ilNavigationHistory
     */
    protected $nav_history;

    /**
     * @var ilErrorHandling
     */
    protected $error;

    /**
     * @var ilHelpGUI
     */
    protected $help;

    /**
     * @var ilTemplate
     */
    protected $tpl;

    /**
     * @var ilLanguage
     */
    protected $lng;

    /**
     * @var ilObjectDefinition
     */
    protected $objDefinition;

    /**
     * @var int
     */
    protected $ref_id;

    /**
     * @var ilObjLearningModule
     */
    protected $lm_obj;

    /**
     * @var ilTree
     */
    protected $tree;

    /**
     * @var int
     */
    protected $obj_id;

    protected int $requested_active_node = 0;
    protected bool $to_page = false;

    /**
    * Constructor
    * @access	public
    */
    public function __construct()
    {
        global $DIC;

        $this->rbacsystem = $DIC->rbac()->system();
        $this->nav_history = $DIC["ilNavigationHistory"];
        $this->error = $DIC["ilErr"];
        $this->help = $DIC["ilHelp"];
        $tpl = $DIC["tpl"];
        $lng = $DIC->language();
        $objDefinition = $DIC["objDefinition"];
        $ilCtrl = $DIC->ctrl();
        $rbacsystem = $DIC->rbac()->system();
        $ilNavigationHistory = $DIC["ilNavigationHistory"];
        $ilErr = $DIC["ilErr"];
        
        $lng->loadLanguageModule("content");
        $lng->loadLanguageModule("lm");

        $this->ref_id = (int) ($_GET["ref_id"] ?? 0);
        $this->obj_id = (int) ($_GET["obj_id"] ?? 0);

        // check write permission
        if (!$rbacsystem->checkAccess("write", $this->ref_id)) {
            $ilErr->raiseError($lng->txt("permission_denied"), $ilErr->MESSAGE);
        }

        $this->ctrl = $ilCtrl;
        $this->tool_context = $DIC->globalScreen()->tool()->context();

        $this->ctrl->saveParameter($this, array("ref_id", "transl"));

        // initiate variables
        $this->tpl = $tpl;
        $this->lng = $lng;
        $this->objDefinition = $objDefinition;

        $this->lm_obj = ilObjectFactory::getInstanceByRefId($this->ref_id);
        $this->tree = new ilTree($this->lm_obj->getId());
        $this->tree->setTableNames('lm_tree', 'lm_data');
        $this->tree->setTreeTablePK("lm_id");


        $ilNavigationHistory->addItem(
            $this->ref_id,
            "ilias.php?baseClass=ilLMEditorGUI&ref_id=" . $this->ref_id,
            "lm"
        );

        $this->requested_active_node = (int) ($_REQUEST["active_node"] ?? 0);
        $this->to_page = (bool) ($_GET["to_page"] ?? false);

        $this->checkRequestParameters();
    }
    
    /**
     * Check request parameters
     * @throws ilCtrlException
     * @throws ilException
     */
    protected function checkRequestParameters()
    {
        $forwards_to_role = $this->ctrl->checkCurrentPathForClass("ilobjrolegui");

        if (!$forwards_to_role && $this->obj_id > 0 && ilLMObject::_lookupContObjID($this->obj_id) != $this->lm_obj->getId()) {
            throw new ilException("Object ID does not match learning module.");
        }
        if ($this->requested_active_node > 0 && ilLMObject::_lookupContObjID($this->requested_active_node) != $this->lm_obj->getId()) {
            throw new ilException("Active node does not match learning module.");
        }
    }
    

    /**
     * @throws ilCtrlException
     * @throws ilException
     */
    public function executeCommand()
    {
        global $DIC;

        $this->tool_context->claim()->repository();

        $cmd = "";

        /** @var ilLocatorGUI $loc */
        $loc = $DIC["ilLocator"];
        $loc->addRepositoryItems($this->ref_id);

        if ($this->to_page) {
            $this->ctrl->setParameterByClass("illmpageobjectgui", "obj_id", $this->obj_id);
            $this->ctrl->redirectByClass(array("ilobjlearningmodulegui", "illmpageobjectgui"), "edit");
        }
        
        $this->showTree();

        $next_class = $this->ctrl->getNextClass($this);

        if ($next_class == "" && ($cmd != "explorer")
            && ($cmd != "showImageMap")) {
            $next_class = "ilobjlearningmodulegui";
        }

        // show footer
        $show_footer = ($cmd == "explorer")
            ? false
            : true;
            
        switch ($next_class) {
            case "ilobjlearningmodulegui":
                $this->main_header($this->lm_obj->getType());
                $lm_gui = new ilObjLearningModuleGUI("", $this->ref_id, true, false);

                $ret = $this->ctrl->forwardCommand($lm_gui);
                if (strcmp($cmd, "explorer") != 0) {
                    $this->displayLocator();
                }
                // (horrible) workaround for preventing template engine
                // from hiding paragraph text that is enclosed
                // in curly brackets (e.g. "{a}", see ilPageObjectGUI::showPage())
                // @todo 6.0
                /*
                $output =  $this->tpl->getSpecial("DEFAULT", true, true, $show_footer,true);
                $output = str_replace("&#123;", "{", $output);
                $output = str_replace("&#125;", "}", $output);
                header('Content-type: text/html; charset=UTF-8');
                echo $output;*/
                $this->tpl->printToStdout();
                break;

            default:
                $ret = $this->$cmd();
                break;
        }
    }

    /**
     * Show tree
     */
    public function showTree()
    {
        $tpl = $this->tpl;

        $this->tool_context->current()->addAdditionalData(ilLMEditGSToolProvider::SHOW_TREE, true);

        $exp = new ilLMEditorExplorerGUI($this, "showTree", $this->lm_obj);
        if (!$exp->handleCommand()) {
//            $tpl->setLeftNavContent($exp->getHTML());
        }
    }
    
    /**
     * output main header (title and locator)
     */
    public function main_header()
    {
        $this->tpl->loadStandardTemplate();

        // content style
        $this->tpl->setCurrentBlock("ContentStyle");
        $this->tpl->setVariable(
            "LOCATION_CONTENT_STYLESHEET",
            ilObjStyleSheet::getContentStylePath($this->lm_obj->getStyleSheetId())
        );
        $this->tpl->parseCurrentBlock();

        // syntax style
        $this->tpl->setCurrentBlock("SyntaxStyle");
        $this->tpl->setVariable(
            "LOCATION_SYNTAX_STYLESHEET",
            ilObjStyleSheet::getSyntaxStylePath()
        );
        $this->tpl->parseCurrentBlock();
    }


    /**
     * Display locator
     */
    public function displayLocator()
    {
        $this->tpl->setLocator();
    }
}
