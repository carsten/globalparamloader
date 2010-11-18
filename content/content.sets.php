<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	
	class contentExtensionGlobalParamLoaderSets extends AdministrationPage {
		protected $_action = '';
		protected $_conditions = array();
		protected $_driver = null;
		protected $_editing = false;
		protected $_errors = array();
		protected $_fields = array();
		protected $_pages = array();
		protected $_prepared = false;
		protected $_status = '';
		protected $_sets = array();
		protected $_uri = null;
		protected $_valid = true;
		
		public function __construct(&$parent){
			parent::__construct($parent);
			
			$this->_uri = URL . '/symphony/extension/globalparamloader';
			$this->_driver = $this->_Parent->ExtensionManager->create('globalparamloader');
		}
		
		public function build($context) {
			if (@$context[0] == 'edit' or @$context[0] == 'new') {
				if ($this->_editing = $context[0] == 'edit') {
					$this->_fields = $this->_driver->getSet((integer)$context[1]);
					$this->_params = $this->_driver->getParameters((integer)$context[1]);
				}
				
				$this->_fields = (isset($_POST['fields']) ? $_POST['fields'] : $this->_fields);
				$this->_params = (isset($_POST['params']) ? $_POST['params'] : $this->_params);
				$this->_status = $context[2];
				$this->_pages = $this->_driver->getPages();
			} else {
				$this->_sets = $this->_driver->getSets();
			}
			
			parent::build($context);
		}
		
		public function __actionNew() {
			$this->__actionEdit();
		}
		
		public function __actionEdit() {
			if (@array_key_exists('delete', $_POST['action'])) {
				$this->__actionEditDelete();
				
			} else {
				$this->__actionEditNormal();
			}
		}
		
		public function __actionEditDelete() {
			$this->_Parent->Database->delete('tbl_gpl_sets', " `id` = '{$this->_fields['id']}'");
			$this->_Parent->Database->delete('tbl_gpl_params', " `set_id` = '{$this->_fields['id']}'");
			
			redirect("{$this->_uri}/sets/");
		}
		
		public function __actionEditNormal() {
			
		// Validate: ----------------------------------------------------------
			
			if (empty($this->_fields['name'])) {
				$this->_errors['name'] = 'Name must not be empty.';
			}
			
			if  (empty($this->_params)) {
				$this->_errors['params'] = 'Parameters must not be empty.';
			}
			
			foreach ($this->_params as $sortorder => $param) {
				if (empty($param['param'])) {
					$this->_errors["{$sortorder}:param"] = 'Parameter must not be empty.';
				}
			}
			
			if (!empty($this->_errors)) {
				$this->_valid = false;
				return;
			}
			
		// Save: --------------------------------------------------------------
			
			$this->_fields['params'] = (integer)count($this->_params);
			if($this->_fields['exclude_page']) $this->_fields['exclude_page'] = implode(',', $this->_fields['exclude_page']);
			
			$this->_Parent->Database->insert($this->_fields, 'tbl_gpl_sets', true);
			$this->_Parent->Database->update($this->_fields, 'tbl_gpl_sets', "`id` = '".$this->_fields['id']."'");
			
			if (!$this->_editing) {
				$redirect_mode = 'created';
				
				$set_id = $this->_Parent->Database->fetchVar('id', 0, "
					SELECT
						s.id
					FROM
						`tbl_gpl_sets` AS s
					ORDER BY
						s.id DESC
					LIMIT 1
				");
				
			} else {
				$redirect_mode = 'saved';
				$set_id = $this->_fields['id'];
			}
			
			// Remove all parameters before adding existing ones
			$this->_Parent->Database->delete('tbl_gpl_params', " `set_id` = '{$this->_fields['id']}'");
			
			foreach ($this->_params as $param) {
				$param['set_id'] = $set_id;
				
				$this->_Parent->Database->insert($param, 'tbl_gpl_params', true);
				
			}
			
			redirect("{$this->_uri}/sets/edit/{$set_id}/{$redirect_mode}/");
		}
		
		public function __viewNew() {
			$this->__viewEdit();
		}
		
		public function __viewEdit() {
		// Status: -----------------------------------------------------------
			
			if (!$this->_valid) $this->pageAlert('
				An error occurred while processing this form.
				<a href="#error">See below for details.</a>',
				AdministrationPage::PAGE_ALERT_ERROR
			);
			
			// Status message:
			if ($this->_status) {
				$action = null;
				
				switch($this->_status) {
					case 'saved': $action = 'updated'; break;
					case 'created': $action = 'created'; break;
					case 'removed': $action = 'removed'; break;
				}
				
				if ($action) $this->pageAlert(
					"Set {$this->_status} successfully. <a href=\"{$this->_uri}/sets/new/\">Create another?</a>",
					AdministrationPage::PAGE_ALERT_NOTICE, array(
						$action, URL, 'extension/globalparamloader/sets/new/'
					)
				);
			}
			
			// Edit:
			if ($this->_action == 'edit') {
				if ($this->_set > 0) {
					$row = $this->_Parent->Database->fetchRow(0, "
						SELECT
							s.*
						FROM
							`tbl_gpl_sets` AS s
						WHERE
							s.id = {$this->_set}
					");
					
					if (!empty($row)) {
						$this->_fields = $row;
					} else {
						$this->_editing = false;
					}
				}
			}
			
		// Header: ------------------------------------------------------------
			
			$this->setPageType('form');
			$this->setTitle('Symphony &ndash; Global Parameter Sets' . (
				$this->_editing ? ' &ndash; ' . $this->_fields['name'] : null
			));
			$this->appendSubheading("<a href=\"{$this->_uri}/sets/\">Parameter Sets</a> &mdash; " . (
				$this->_editing ? $this->_fields['name'] : 'Untitled'
			));
			
		// Form: --------------------------------------------------------------
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', 'Essentials'));
			
			if (!empty($this->_fields['id'])) {
				$fieldset->appendChild(Widget::Input("fields[id]", $this->_fields['id'], 'hidden'));
			}
			
			$label = Widget::Label('Name');
			$label->appendChild(Widget::Input(
				'fields[name]',
				General::sanitize(@$this->_fields['name'])
			));
			
			if (isset($this->_errors['name'])) {
				$label = Widget::wrapFormElementWithError($label, $this->_errors['name']);
			}
			
			$fieldset->appendChild($label);
			
			$this->Form->appendChild($fieldset);
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', 'Parameters'));
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'subsection');
			$div->appendChild(new XMLElement('h3', 'Parameters'));
			$ol = new XMLElement('ol');
			
			// Add existing parameters:
			if(isset($this->_params)) {
				foreach ($this->_params as $sortorder => $param) {
					$wrapper = new XMLElement('li');
				
					$this->displayParameter($wrapper, $sortorder, $param);
				
					$ol->appendChild($wrapper);
				}
			}
			
			// Add parameter set:
			$wrapper = new XMLElement('li');
			$wrapper->setAttribute('class', 'template');
			
			$this->displayParameter($wrapper, '-1', array(
				'type'		=> 'Parameter definition'
			));
			
			$ol->appendChild($wrapper);
			
			$div->appendChild($ol);
			$fieldset->appendChild($div);
			
			$this->Form->appendChild($fieldset);
					
		// Excluded Pages --------------------------------------------------------
		
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', 'Pages'));
			
			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');
			
			$this->viewIndexPages($group, $this->_fields['id']);
			
			$fieldset->appendChild($group);
			
			$this->Form->appendChild($fieldset);
		
		// Footer: ------------------------------------------------------------
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(
				Widget::Input('action[save]',
					($this->_editing ? 'Save Changes' : 'Create Set'),
					'submit', array(
						'accesskey'		=> 's'
					)
				)
			);
			
			if ($this->_editing) {
				$button = new XMLElement('button', 'Delete');
				$button->setAttributeArray(array(
					'name'		=> 'action[delete]',
					'class'		=> 'confirm delete',
					'title'		=> 'Delete this set'
				));
				$div->appendChild($button);
			}
			
			$this->Form->appendChild($div);
		}
		
		protected function displayParameter(&$wrapper, $sortorder, $param) {
			$wrapper->appendChild(new XMLElement('h4', ucwords($param['type'])));
			$wrapper->appendChild(Widget::Input("params[{$sortorder}][type]", $param['type'], 'hidden'));
			
			if (!empty($param['id'])) {
				$wrapper->appendChild(Widget::Input("params[{$sortorder}][id]", $param['id'], 'hidden'));
			}
			
		// Parameter name ------------------------------------------------------------
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'group');
			
			$label = Widget::Label('Parameter name');
			$label->appendChild(Widget::Input(
				"params[{$sortorder}][param]",
				General::sanitize($param['param'])
			));
			
			if (isset($this->_errors["{$sortorder}:param"])) {
				$label = Widget::wrapFormElementWithError($label, $this->_errors["{$sortorder}:param"]);
			}
			
			$div->appendChild($label);
			
		// Parameter Value --------------------------------------------------------
			
			$label = Widget::Label('Parameter value');
			$label->appendChild(Widget::Input(
				"params[{$sortorder}][value]",
				General::sanitize($param['value'])
			));
			
			if (isset($this->_errors["{$sortorder}:value"])) {
				$label = Widget::wrapFormElementWithError($label, $this->_errors["{$sortorder}:value"]);
			}
			
			$div->appendChild($label);
			$wrapper->appendChild($div);
		}
		
		public function viewIndexPages($context, $set_id = Null) {
			$pages = $this->_Parent->Database->fetch("
				SELECT
					p.*
				FROM
					tbl_pages AS p
				ORDER BY
					p.sortorder ASC
			");
			$options = array();
			
			foreach ($pages as $page) {
				$selected = $this->_driver->isPageSelected($page['id'], $set_id);
				
				$options[] = array(
					$page['id'], $selected, '/' . $this->_Parent->resolvePagePath($page['id'])
				);
			}
			
			$section = Widget::Label('Excluded Pages');
			$section->appendChild(Widget::Select(
				'fields[exclude_page][]', $options, array(
					'multiple'	=> 'multiple'
				)
			));
			
			$context->appendChild($section);
		}
		
	/*-------------------------------------------------------------------------
		Index
	-------------------------------------------------------------------------*/
		
		public function __actionIndex() {
			$checked = @array_keys($_POST['items']);
			
			if (is_array($checked) and !empty($checked)) {
				switch ($_POST['with-selected']) {
					case 'delete':
						foreach ($checked as $set_id) {
							$this->_Parent->Database->query("
								DELETE FROM
									`tbl_gpl_sets`
								WHERE
									`id` = {$set_id}
							");
							
							$this->_Parent->Database->query("
								DELETE FROM
									`tbl_gpl_params`
								WHERE
									`set_id` = {$set_id}
							");
						}
						
						redirect("{$this->_uri}/sets/");
						break;
				}
			}
		}
		
		public function __viewIndex() {
			$this->setPageType('table');
			$this->setTitle('Symphony &ndash; Global Parameter Sets');
			
			$this->appendSubheading('Parameter Sets', Widget::Anchor(
				'Create New', "{$this->_uri}/sets/new/",
				'Create a new parameter set', 'create button'
			));
			
			$tableHead = array(
				array('Parameter Set Name', 'col'),
				array('Parameters', 'col')
			);	
			
			$tableBody = array();
			
			if (!is_array($this->_sets) or empty($this->_sets)) {
				$tableBody = array(
					Widget::TableRow(array(Widget::TableData(__('None Found.'), 'inactive', null, count($tableHead))))
				);
				
			} else {
				foreach ($this->_sets as $set) {
					$set = (object)$set;
					
					$col_name = Widget::TableData(
						Widget::Anchor(
							$set->name,
							"{$this->_uri}/sets/edit/{$set->id}/"
						)
					);
					$col_name->appendChild(Widget::Input("items[{$set->id}]", null, 'checkbox'));
					
					if (!empty($set->params)) {
						$col_params = Widget::TableData($set->params);
						
					} else {
						$col_params = Widget::TableData('None', 'inactive');
					}
					
					$tableBody[] = Widget::TableRow(array($col_name, $col_params), null);
				}
			}
			
			$table = Widget::Table(
				Widget::TableHead($tableHead), null, 
				Widget::TableBody($tableBody)
			);
			
			$this->Form->appendChild($table);
			
			$actions = new XMLElement('div');
			$actions->setAttribute('class', 'actions');
			
			$options = array(
				array(null, false, 'With Selected...'),
				array('delete', false, 'Delete')									
			);

			$actions->appendChild(Widget::Select('with-selected', $options));
			$actions->appendChild(Widget::Input('action[apply]', 'Apply', 'submit'));
			
			$this->Form->appendChild($actions);		
		}
	}
	
?>