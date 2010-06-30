<?php
/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once './Services/Calendar/classes/class.ilCalendarEntry.php';

/**
* Consultation hour appointments
*
* @author Stefan Meyer <meyer@leifos.com>
*
* @version $Id$
*
* @ingroup ServicesCalendar
*/
class ilConsultationHourAppointments
{
	
	/**
	 * Get all appointment ids
	 * @param object $a_user_id
	 * @return 
	 */
	public static function getAppointmentIds($a_user_id)
	{
		global $ilDB;
		
		$query = "SELECT ce.cal_id FROM cal_entries ce ".
			"JOIN cal_cat_assignments cca ON ce.cal_id = cca.cal_id ".
			"JOIN cal_categories cc ON cca.cat_id = cc.cat_id ".
			"WHERE obj_id = ".$ilDB->quote($a_user_id,'integer')." ".
			"AND type = ".$ilDB->quote(ilCalendarCategory::TYPE_CH);
		$res = $ilDB->query($query);
		$entries = array();
		while($row = $res->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$entries[] = $row->cal_id;
		}
		return $entries;
	}
	
	/**
	 * Get all appointments
	 * @return 
	 */
	public static function getAppointments($a_user_id)
	{
		$entries = array();
		foreach(self::getAppointmentIds($a_user_id) as $app_id)
		{
			$entries[] = new ilCalendarEntry($app_id);
		}
		return $entries;
	}
}
?>