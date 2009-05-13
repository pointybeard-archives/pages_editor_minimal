<?php
	
	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.eventmanager.php');
	require_once(TOOLKIT . '/class.datasourcemanager.php');
	require_once(TOOLKIT . '/class.xsltprocess.php');
		
	class contentExtensionPages_editor_minimalEdit extends AdministrationPage {
		private $_errors;
		private $_driver;
		
		function __construct(&$parent){
			parent::__construct($parent);
			$this->_driver = Administration::instance()->ExtensionManager->create('pages_editor_minimal');
		}
		
		function view(){
			
			$this->setPageType('form');
			$this->addStylesheetToHead(URL . '/extensions/pages_editor_minimal/assets/screen.css', 'screen', 1200);
						
			$fields = array();

			if(!$page_id = $this->_context[0]) redirect(URL . '/symphony/blueprints/pages/');
				
			if(!$existing = $this->_Parent->Database->fetchRow(0, "SELECT * FROM `tbl_pages` WHERE `id` = '$page_id' LIMIT 1"))
				$this->_Parent->customError(E_USER_ERROR, __('Page not found'), __('The page you requested to edit does not exist.'), false, true, 'error', array('header' => 'HTTP/1.0 404 Not Found'));
			
			
			if(isset($this->_context[1])){
				switch($this->_context[1]){
					
					case 'saved':
						$this->pageAlert(
							__(
								'Page updated at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all Pages</a>', 
								array(
									DateTimeObj::get(__SYM_TIME_FORMAT__), 
									URL . '/symphony/blueprints/pages/new/', 
									URL . '/symphony/blueprints/pages/' 
								)
							), 
							Alert::SUCCESS);						
						
						break;
						
					case 'created':
						$this->pageAlert(
							__(
								'Page created at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all Pages</a>', 
								array(
									DateTimeObj::get(__SYM_TIME_FORMAT__), 
									URL . '/symphony/blueprints/pages/new/', 
									URL . '/symphony/blueprints/pages/' 
								)
							), 
							Alert::SUCCESS);
						break;
					
				}
			}
			
			if(isset($_POST['fields'])){
				$fields = $_POST['fields'];
			}
			
			else{
				
				$fields = $existing;

				$types = $this->_Parent->Database->fetchCol('type', "SELECT `type` FROM `tbl_pages_types` WHERE page_id = '$page_id' ORDER BY `type` ASC");		
				$fields['type'] = @implode(', ', $types);

				$fields['data_sources'] = preg_split('/,/i', $fields['data_sources'], -1, PREG_SPLIT_NO_EMPTY);
				$fields['events'] = preg_split('/,/i', $fields['events'], -1, PREG_SPLIT_NO_EMPTY);
				$fields['body'] = @file_get_contents(PAGES . '/' . trim(str_replace('/', '_', $fields['path'] . '_' . $fields['handle']), '_') . ".xsl");
			}
			
			
			$title = $fields['title'];
			if(trim($title) == '') $title = $existing['title'];
			
			$this->setTitle(__(($title ? '%1$s &ndash; %2$s &ndash; %3$s' : '%1$s &ndash; %2$s'), array(__('Symphony'), __('Pages'), $title)));
#			$this->appendSubheading(($title ? $title : __('Untitled')));
			$label = Widget::Label(__('Title'));		
			$label->appendChild(Widget::Input('fields[title]', General::sanitize($fields['title'])));
			$this->Form->appendChild((isset($this->_errors['title']) ? $this->wrapFormElementWithError($label, $this->_errors['title']) : $label));
						
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'primary');
			
			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');
			$label = Widget::Label(__('Events'));
			
			$EventManager = new EventManager($this->_Parent);
			$events = $EventManager->listAll();
			
			$options = array();
			if(is_array($events) && !empty($events)){		
				foreach($events as $name => $about) $options[] = array($name, @in_array($name, $fields['events']), $about['name']);
			}

			$label->appendChild(Widget::Select('fields[events][]', $options, array('multiple' => 'multiple')));		
			$group->appendChild($label);

			$label = Widget::Label(__('Data Sources'));
			
			$DSManager = new DatasourceManager($this->_Parent);
			$datasources = $DSManager->listAll();
			
			$options = array();
			if(is_array($datasources) && !empty($datasources)){		
				foreach($datasources as $name => $about) $options[] = array($name, @in_array($name, $fields['data_sources']), $about['name']);
			}

			$label->appendChild(Widget::Select('fields[data_sources][]', $options, array('multiple' => 'multiple')));
			$group->appendChild($label);
			$fieldset->appendChild($group);			
			
			$this->Form->appendChild($fieldset);
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'secondary');			

			
			$label = Widget::Label(__('URL Handle'));
			$label->appendChild(Widget::Input('fields[handle]', $fields['handle']));
			$fieldset->appendChild((isset($this->_errors['handle']) ? $this->wrapFormElementWithError($label, $this->_errors['handle']) : $label));
			
			$pages = $this->_Parent->Database->fetch("SELECT * FROM `tbl_pages` WHERE `id` != '{$page_id}' ORDER BY `title` ASC");
			
			$label = Widget::Label(__('Parent Page'));
			
			$options = array(
				array('', false, '/')
			);
			
			if (is_array($pages) and !empty($pages)) {
				if (!function_exists('__compare_pages')) {
					function __compare_pages($a, $b) {
						return strnatcasecmp($a[2], $b[2]);
					}
				}
				
				foreach ($pages as $page) {
					$options[] = array(
						$page['id'], $fields['parent'] == $page['id'],
						'/' . $this->_Parent->resolvePagePath($page['id'])
					);
				}
				
				usort($options, '__compare_pages');
			}
			
			$label->appendChild(Widget::Select('fields[parent]', $options));
			$fieldset->appendChild($label);

			$label = Widget::Label(__('URL Parameters'));
			$label->appendChild(Widget::Input('fields[params]', $fields['params']));				
			$fieldset->appendChild($label);

			$div3 = new XMLElement('div');
			$label = Widget::Label(__('Page Type'));
			$label->appendChild(Widget::Input('fields[type]', $fields['type']));
			$div3->appendChild((isset($this->_errors['type']) ? $this->wrapFormElementWithError($label, $this->_errors['type']) : $label));
			
			$ul = new XMLElement('ul');
			$ul->setAttribute('class', 'tags');
			if($types = $this->__fetchAvailablePageTypes()) foreach($types as $type) $ul->appendChild(new XMLElement('li', $type));
			$div3->appendChild($ul);
			
			$fieldset->appendChild($div3);

			$this->Form->appendChild($fieldset);
			
					
			/*$utilities = General::listStructure(UTILITIES, array('xsl'), false, 'asc', UTILITIES);
			$utilities = $utilities['filelist'];			
			
			if(is_array($utilities) && !empty($utilities)){
			
				$div = new XMLElement('div');
				$div->setAttribute('class', 'secondary');
				
				$h3 = new XMLElement('h3', __('Utilities'));
				$h3->setAttribute('class', 'label');
				$div->appendChild($h3);
				
				$ul = new XMLElement('ul');
				$ul->setAttribute('id', 'utilities');
			
				$i = 0;
				foreach($utilities as $util){
					$li = new XMLElement('li');

					if ($i++ % 2 != 1) {
						$li->setAttribute('class', 'odd');
					}

					$li->appendChild(Widget::Anchor($util, URL . '/symphony/blueprints/utilities/edit/' . str_replace('.xsl', '', $util) . '/', NULL));
					$ul->appendChild($li);
				}
			
				$div->appendChild($ul);
			
				$this->Form->appendChild($div);
							
			}*/
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(Widget::Input('action[save]', __('Save Changes'), 'submit', array('accesskey' => 's')));
			
			$button = new XMLElement('button', __('Delete'));
			$button->setAttributeArray(array('name' => 'action[delete]', 'class' => 'confirm delete', 'title' => __('Delete this page')));
			$div->appendChild($button);
			
			$this->Form->appendChild($div);

		}
		
		
		function action(){
			
			if(!$page_id = $this->_context[0]) redirect(URL . '/symphony/blueprints/pages/');

			if(@array_key_exists('delete', $_POST['action'])) {

				## TODO: Fix Me
				###
				# Delegate: Delete
				# Description: Prior to deletion. Provided with Page's database ID
				//$ExtensionManager->notifyMembers('Delete', getCurrentPage(), array('page' => $page_id));

			    $page = $this->_Parent->Database->fetchRow(0, "SELECT * FROM tbl_pages WHERE `id` = '$page_id'");

				$filename = $page['path'] . '_' . $page['handle'];
				$filename = trim(str_replace('/', '_', $filename), '_');

				$this->_Parent->Database->delete('tbl_pages', " `id` = '$page_id'");
				$this->_Parent->Database->delete('tbl_pages_types', " `page_id` = '$page_id'");	  
				$this->_Parent->Database->query("UPDATE tbl_pages SET `sortorder` = (`sortorder` + 1) WHERE `sortorder` < '$page_id'");

				General::deleteFile(PAGES . "/$filename.xsl");

				redirect(URL . '/symphony/blueprints/pages/');

			}

			elseif(@array_key_exists('save', $_POST['action'])){

				$fields = $_POST['fields'];
				
				$this->_errors = array();

				if(!isset($fields['title']) || trim($fields['title']) == '') $this->_errors['title'] = __('Title is a required field');

				if(trim($fields['type']) != '' && preg_match('/(index|404|403)/i', $fields['type'])){
					
					$haystack = strtolower($fields['type']);
					
					if(preg_match('/\bindex\b/i', $haystack, $matches) && $row = $this->_Parent->Database->fetchRow(0, "SELECT * FROM `tbl_pages_types` WHERE `page_id` != '$page_id' AND `type` = 'index' LIMIT 1")){					
						$this->_errors['type'] = __('An index type page already exists.');
					}
					
					elseif(preg_match('/\b404\b/i', $haystack, $matches) && $row = $this->_Parent->Database->fetchRow(0, "SELECT * FROM `tbl_pages_types` WHERE `page_id` != '$page_id' AND `type` = '404' LIMIT 1")){	
						$this->_errors['type'] = __('A 404 type page already exists.');
					}	

					elseif(preg_match('/\b403\b/i', $haystack, $matches) && $row = $this->_Parent->Database->fetchRow(0, "SELECT * FROM `tbl_pages_types` WHERE `page_id` != '$page_id' AND `type` = '403' LIMIT 1")){	
						$this->_errors['type'] = __('A 403 type page already exists.');
					}					
				}
				
				if(empty($this->_errors)){

					## Manipulate some fields
					//$fields['sortorder'] = $this->_Parent->Database->fetchVar('next', 0, "SELECT MAX(sortorder) + 1 as `next` FROM `tbl_pages` LIMIT 1");
					//
					//if(empty($fields['sortorder']) || !is_numeric($fields['sortorder'])) $fields['sortorder'] = 1;
										
					$autogenerated_handle = false;
					
					if(trim($fields['handle'] ) == ''){ 
						$fields['handle'] = $fields['title'];
						$autogenerated_handle = true;
					}
					
					$fields['handle'] = Lang::createHandle($fields['handle']);		

					if($fields['params']) $fields['params'] = trim(preg_replace('@\/{2,}@', '/', $fields['params']), '/');

					## Clean up type list
					$types = preg_split('/,\s*/', $fields['type'], -1, PREG_SPLIT_NO_EMPTY);
					$types = @array_map('trim', $types);
					unset($fields['type']);
					
					//if(trim($fields['type'])) $fields['type'] = preg_replace('/\s*,\s*/i', ', ', $fields['type']);
					//else $fields['type'] = NULL;		

					## Manipulate some fields
					$fields['parent'] = ($fields['parent'] != 'None' ? $fields['parent'] : NULL);			

					$fields['data_sources'] = @implode(',', $fields['data_sources']);			
					$fields['events'] = @implode(',', $fields['events']);	

					$fields['path'] = NULL;
					if($fields['parent']) $fields['path'] = $this->_Parent->resolvePagePath(intval($fields['parent']));
				
					$new_filename = trim(str_replace('/', '_', $fields['path'] . '_' . $fields['handle']), '_');

					$current = $this->_Parent->Database->fetchRow(0, "SELECT * FROM `tbl_pages` WHERE `id` = '$page_id' LIMIT 1");	

					$current_filename = $current['path'] . '_' . $current['handle'];
					$current_filename = trim(str_replace('/', '_', $current_filename), '_');
					
					## Duplicate
					if($this->_Parent->Database->fetchRow(0, "SELECT * FROM `tbl_pages` 
										 WHERE `handle` = '" . $fields['handle'] . "' 
										 AND `id` != '$page_id' 
										 AND `path` ".($fields['path'] ? " = '".$fields['path']."'" : ' IS NULL')." 
										 LIMIT 1")){
											
						if($autogenerated_handle) $this->_errors['title'] = __('A page with that title %s already exists', array(($fields['parent'] ? __('and parent') : '')));
						else $this->_errors['handle'] = __('A page with that handle %s already exists', array(($fields['parent'] ? __('and parent') : '')));

					}
					
					else{	
						
						$fields['body'] = file_get_contents(PAGES . "/$current_filename.xsl");

						## Write the file
						if($new_filename != $current_filename && !$write = General::writeFile(PAGES . "/$new_filename.xsl" , $fields['body'], $this->_Parent->Configuration->get('write_mode', 'file'))){
							$this->pageAlert(__('Page could not be written to disk. Please check permissions on <code>/workspace/pages</code>.'), Alert::ERROR); 			
						}
						
						## Write Successful, add record to the database
						else{
							
							if($new_filename != $current_filename) @unlink(PAGES . "/$current_filename.xsl");
							
							## No longer need the body text
							unset($fields['body']);

							## Insert the new data
							if(!$this->_Parent->Database->update($fields, 'tbl_pages', "`id` = '$page_id'")) $this->pageAlert(__('Unknown errors occurred while attempting to save. Please check your <a href="%s">activity log</a>.', array(URL.'/symphony/system/log/')), Alert::ERROR);
							
							else{
								
								$this->_Parent->Database->delete('tbl_pages_types', " `page_id` = '$page_id'");
								
								if(is_array($types) && !empty($types)){
									foreach($types as $type) $this->_Parent->Database->insert(array('page_id' => $page_id, 'type' => $type), 'tbl_pages_types');
								}

								## TODO: Fix Me
								###
								# Delegate: Edit
								# Description: After saving the page. The Page's database ID is provided.
								//$ExtensionManager->notifyMembers('Edit', getCurrentPage(), array('page_id' => $page_id));

			                    redirect(URL . "/symphony/extension/pages_editor_minimal/edit/$page_id/saved/");

							}
						}
					}
				}
				
				if(is_array($this->_errors) && !empty($this->_errors)){
					$this->pageAlert(__('An error occurred while processing this form. <a href="#error">See below for details.</a>'), Alert::ERROR);
				}
			}
		}
	
	}


