<?php
require_once("gacl_admin.inc.php");

//GET takes precedence.
if ($_GET['group_type'] != '') {
	$group_type = $_GET['group_type'];
} else {
	$group_type = $_POST['group_type'];	
}

switch(strtolower(trim($group_type))) {
    case 'axo':
        $group_type = 'axo';
	$table = $gacl_api->_db_table_prefix . 'axo';
		$group_table = $gacl_api->_db_table_prefix . 'axo_groups';
		$group_sections_table = $gacl_api->_db_table_prefix . 'axo_sections';
		$group_map_table = $gacl_api->_db_table_prefix . 'axo_groups_map';
        break;
    default:
        $group_type = 'aro';
	$table = $gacl_api->_db_table_prefix . 'aro';
		$group_table = $gacl_api->_db_table_prefix . 'aro_groups';
		$group_sections_table = $gacl_api->_db_table_prefix . 'aro_sections';
		$group_map_table = $gacl_api->_db_table_prefix . 'aro_groups_map';
        break;
}

switch ($_POST['action']) {
    case 'Delete':
	    $gacl_api->debug_text("Delete!!");

		//Parse the form values
		//foreach ($_POST['delete_assigned_aro'] as $aro_value) {
		while (list(,$object_value) = @each($_POST['delete_assigned_object'])) {						
				$split_object_value = explode("^", $object_value);
				$selected_object_array[$split_object_value[0]][] = $split_object_value[1];
		}

        //Insert Object -> GROUP mappings
        while (list($object_section_value,$object_array) = @each($selected_object_array)) {
            $gacl_api->debug_text("Assign: Object ID: $object_section_value to Group: $_POST[group_id]");   

			foreach ($object_array as $object_value) {
                $gacl_api->del_group_object($_POST['group_id'], $object_section_value, $object_value, $group_type);
			}
        }
         
        //Return page.
        $gacl_api->return_page("$PHP_SELF?group_type=".$_POST['group_type']."&group_id=".$_POST['group_id']."");
		
        break;
    case 'Submit':
        $gacl_api->debug_text("Submit!!");

		//showarray($_POST['selected_'.$_POST['group_type']]);
		//Parse the form values
		//foreach ($_POST['selected_aro'] as $aro_value) {
		while (list(,$object_value) = @each($_POST['selected_'.$_POST['group_type']])) {
				$split_object_value = explode("^", $object_value);
				$selected_object_array[$split_object_value[0]][] = $split_object_value[1];
		}

        //Insert ARO -> GROUP mappings
        while (list($object_section_value,$object_array) = @each($selected_object_array)) {
            $gacl_api->debug_text("Assign: Object ID: $object_section_value to Group: $_POST[group_id]");   

			foreach ($object_array as $object_value) {
				$gacl_api->add_group_object($_POST['group_id'], $object_section_value, $object_value, $group_type);
			}
        }
                
        $gacl_api->return_page("$PHP_SELF?group_type=".$_POST['group_type']."&group_id=".$_POST['group_id']."");

        break;    
    default:
        //
        //Grab all ARO sections for select box
        //
        $query = "select value, name from $group_sections_table order by order_value";
        $rs = $db->Execute($query);

        $rows = $rs->GetRows();

        //showarray($rows);

        $i=0;
        while (list(,$row) = @each($rows)) {
            list($id, $value) = $row;
            
            if ($i==0) {
                $section_value=$value;   
            }

            $options_sections[$id] = $value;
            
            $i++;
        }

        //showarray($options_aro_sections);
        $smarty->assign("options_sections", $options_sections);
        $smarty->assign("section_value", $section_value);

        //
        //Grab all ARO's for select box
        //
        $query = "select section_value, value, name from $table order by section_value, order_value limit $gacl_api->_max_select_box_items";
        $rs = $db->Execute($query);
        $rows = $rs->GetRows();

        $js_array_name = $group_type;
        //Init the main aro js array.
        $js_array = "var options = new Array();\n";
        $js_array .= "options['$js_array_name'] = new Array();\n";
        while (list(,$row) = @each($rows)) {
            list($section_value, $value, $name) = $row;
            
            //Prepare javascript code for dynamic select box.
            //Init the javascript sub-array.
            if ($section_value != $tmp_section_value) {
                $i=0;

                $js_array .= "options['$js_array_name']['$section_value'] = new Array();\n";
            }

            //Add each select option for the section
            $js_array .= "options['$js_array_name']['$section_value'][$i] = new Array('$value', '$name');\n";
            
            $tmp_section_value = $section_value;
            $i++;
        }

        $smarty->assign("js_array", $js_array);
        $smarty->assign("js_array_name", $js_array_name);


        //Grab list of assigned Objects
        $query = "select
										b.section_value,
                                        b.value,
                                        b.name,
                                        c.name
                            from    $group_map_table a,
                                        $table b,
                                        $group_sections_table c
                            where   a.group_id = $_GET[group_id]
                                        AND a.section_value=b.section_value
                                        AND a.value=b.value
                                        AND b.section_value=c.value
                            order by c.name, b.name";
        //$rs = $db->Execute($query);
        $rs = $db->pageexecute($query, $gacl_api->_items_per_page, $_GET['page']);
        $rows = $rs->GetRows();

        $i=0;
        while (list(,$row) = @each($rows)) {
            list($section_value, $value, $name, $section) = $row;
            
            $object_rows[] = array(
								'section_value' => $section_value,
                                'value' => $value,
                                'name' => $name,
                                'section' => $section
                            );

        }
        //showarray($aros);
        
        $smarty->assign("rows", $object_rows);
        
		//Get group name.
		$group_data = $gacl_api->get_group_data($_GET['group_id'], $group_type);		
        $smarty->assign("group_name", $group_data[2]);
        
        $smarty->assign("group_id", $_GET['group_id']);
        
        $smarty->assign("total_objects", $rs->_maxRecordCount);
        
        $smarty->assign("paging_data", $gacl_api->get_paging_data($rs));
        
        break;
}

$smarty->assign("group_type", $group_type);
$smarty->assign("return_page", $_SERVER['REQUEST_URI'] );

$smarty->assign("phpgacl_version", $gacl_api->get_version() );
$smarty->assign("phpgacl_schema_version", $gacl_api->get_schema_version() );

$smarty->display('phpgacl/assign_group.tpl');
?>
