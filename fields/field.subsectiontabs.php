<?php

	/**
	 * @package fields
	 */
	/**
	 * This field provides a tabbed subsection management. 
	 */
	Class fieldSubsectiontabs extends Field {

		function __construct(&$parent) {
			parent::__construct($parent);
			$this->_name = __('Subsection Tabs');
			$this->_required = true;
		}
		
		function mustBeUnique(){
			return true;
		}

		function displaySettingsPanel(&$wrapper, $errors=NULL) {
		
			// Basics
			$wrapper->appendChild(new XMLElement('h4', $this->name()));
			$wrapper->appendChild(Widget::Input('fields[' . $this->get('sortorder') . '][type]', $this->handle(), 'hidden'));
			$wrapper->appendChild(Widget::Input('fields[' . $this->get('sortorder') . '][label]', __('Subsection Tabs'), 'hidden'));
		
			// Existing field
			if($this->get('id')) {
				$wrapper->appendChild(Widget::Input('fields['.$this->get('sortorder').'][id]', $this->get('id'), 'hidden'));
			}
			
			// Settings
			$group = new XMLElement('div', NULL, array('class' => 'group'));
			$wrapper->appendChild($group);
						
			// Subsection
			$sectionManager = new SectionManager(Symphony::Engine());
		  	$sections = $sectionManager->fetch(NULL, 'ASC', 'name');
		  	$options = array();

			// Options			
			if(is_array($sections) && !empty($sections)) {
				foreach($sections as $section) {
					$options[] = array(
						$section->get('id'), 
						($section->get('id') == $this->get('subsection_id')), 
						$section->get('name')
					);
				}
			}
			
			$label = new XMLElement('label', __('Subsection'));
			$selection = Widget::Select('fields[' . $this->get('sortorder') . '][subsection_id]', $options);
			$label->appendChild($selection);
			$group->appendChild($label);

			// Field location
			$group->appendChild($this->buildLocationSelect($this->get('location'), 'fields['.$this->get('sortorder').'][location]'));
			
			// Static tab names
			$label = new XMLElement('label', __('Static tab names') . '<i>' . __('List of comma-separated predefined tabs') . '</i>');
			$tabs = Widget::Input('fields['.$this->get('sortorder').'][static_tabs]', $this->get('static_tabs'));
			$label->appendChild($tabs);
			$wrapper->appendChild($label);
			
			// Allow dynamic tabs
			$checkbox = Widget::Input('fields['.$this->get('sortorder').'][allow_dynamic_tabs]', 1, 'checkbox');
			if($this->get('allow_dynamic_tabs') == 1) {
				$checkbox->setAttribute('checked', 'checked');
			}
			$label = new XMLElement('label', $checkbox->generate() . ' ' . __('Allow creation of dynamic tabs'), array('class' => 'meta'));
			$wrapper->appendChild($label);

			// General
			$fieldset = new XMLElement('fieldset');
			$this->appendShowColumnCheckbox($fieldset);
			$wrapper->appendChild($fieldset);
		}

		public function commit(){

			// Prepare commit
			if(!parent::commit()) return false;
			if($this->get('id') === false) return false;

			// Set up fields
			$fields = array();
			$fields['field_id'] = $this->get('id');
			$fields['subsection_id'] = $this->get('subsection_id');
			$fields['static_tabs'] = $this->get('static_tabs');
			$fields['allow_dynamic_tabs'] = ($this->get('allow_dynamic_tabs') ? 1 : 0);

			// Delete old field settings
			Symphony::Database()->query(
				"DELETE FROM `tbl_fields_" . $this->handle() . "` WHERE `field_id` = '" . $this->get('id') . "' LIMIT 1"
			);

			// Save new field setting
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());
		}

		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL) {

			// Append assets
			Administration::instance()->Page->addScriptToHead(URL . '/extensions/subsectionmanager/assets/subsectiontabs.publish.js', 101, false);
			Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/subsectionmanager/assets/subsectiontabs.publish.css', 'screen', 102, false);
			
			// Store settings
			if($this->get('allow_dynamic_tabs') == 1) {
				$settings = 'allow_dynamic_tabs';
			}
			
			// Label
			$label = Widget::Label(__('Subsection Tabs'), NULL, $settings);
			$wrapper->appendChild($label);

			// Container
			$container = new XMLElement('span', NULL, array('class' => 'frame'));
			$list = new XMLElement('ul');
			$container->appendChild($list);
			$label->appendChild($container);
			
			// Get entry ID
			$page = Symphony::Engine()->getPageCallback();
			$entry_id = $page['context']['entry_id'];
			
			// Get subsection name
			$subsection = Symphony::Database()->fetchVar('handle', 0, 
				"SELECT `handle` 
				FROM `tbl_sections`
				WHERE `id`= " . $this->get('subsection_id') . "
				LIMIT 1"
			);

			// Fetch existing tabs
			$existing_tabs = array();
			if(isset($entry_id)) {
				$current = Symphony::Database()->fetch(
					"SELECT `relation_id`, `tab`
					FROM `tbl_entries_data_" . $this->get('id') . "`
					WHERE `entry_id` = " . $entry_id . "
					LIMIT 100"
				);
				
				// Create relations
				foreach($current as $tab) {
					$existing_tabs[$tab['tab']] = $tab['relation_id'];
				}
			}
			
			// Static tabs
			if($this->get('static_tabs') != '') {
				$static_tabs = explode(',', $this->get('static_tabs'));
				
				// Create tab				
				foreach($static_tabs as $tab) {
					$tab = trim($tab);

					// Existing tab
					if(array_key_exists($tab, $existing_tabs) && $existing_tabs[$tab] !== NULL) {
						$list->appendChild($this->__createTab(
							$tab, 
							URL . '/symphony/publish/' . $subsection . '/edit/' . $existing_tabs[$tab],
							$existing_tabs[$tab]
						));
						
						// Unset
						unset($existing_tabs[$tab]);
					}
					
					// New tab
					else {
						$list->appendChild($this->__createTab(
							$tab, 
							URL . '/symphony/publish/' . $subsection . '/new/'
						));
					}
				}
			}
			
			// Dynamic tabs
			if($this->get('allow_dynamic_tabs') == 1) {
				foreach($existing_tabs as $tab => $id) {
					
					// Get mode
					if(empty($id)) {
						$mode = 'new';
					}
					else {
						$mode = 'edit';
					}					
					
					// Append tab
					$list->appendChild($this->__createTab(
						$tab, 
						URL . '/symphony/publish/' . $subsection . '/' . $mode . '/' . $id,
						$id
					));
				}
			}
			
			// No tabs yet
			if($this->get('static_tabs') == '' && empty($existing_tabs)) {
				$list->appendChild($this->__createTab(
					__('Untitled'), 
					URL . '/symphony/publish/' . $subsection . '/new/'
				));				
			}
			
			// Field ID
			$input = Widget::Input('field[subsection-tabs][new]', URL . '/symphony/publish/' . $subsection . '/new/', 'hidden');
			$wrapper->appendChild($input);

			return $wrapper;
		}

		private function __createTab($name, $link, $id=NULL) {
			$item = new XMLElement('li');
			
			// Relation ID
			$storage = Widget::Input('fields[subsection-tabs][relation_id][]', $id, 'hidden');
			$item->appendChild($storage);
			
			// Relation ID
			$storage = Widget::Input('fields[subsection-tabs][tab][]', trim($name), 'hidden');
			$item->appendChild($storage);
			
			// Link to subentry
			$link = new XMLElement('a', trim($name), array('href' => $link));
			$item->appendChild($link);
			
			// Return tab
			return $item;
		}
		
		function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL) {
			$status = self::__OK__;
			if(empty($data)) return NULL;
			
/*			$result = array('relation_id' => array(), 'tab' => array());
			for($i = 0; $i < count($data['relation_id']); $i++) {
				if(!empty($data['relation_id'][$i])) {
					$result['relation_id'][] = $data['relation_id'][$i];
					$result['tab'][] = $data['tab'][$i];
				}
			}
			
			return $result;*/
			return $data;
		}
		
		function createTable(){
			return Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `relation_id` int(11) unsigned DEFAULT NULL,
				  `tab` varchar(255) NOT NULL,
				  PRIMARY KEY (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `relation_id` (`relation_id`)
				) TYPE=MyISAM;"
			);
		}

	}