<?php

	require_once(TOOLKIT . '/class.datasource.php');
	
	class Extension_GlobalParamLoader extends Extension {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		public static $params = array();
		
		public function about() {
			return array(
				'name'			=> 'Global Parameter Loader',
				'version'		=> '1.1.6',
				'release-date'	=> '2009-04-07',
				'author'		=> array(
					'name'			=> 'Carsten de Vries',
					'website'		=> 'http://www.vrieswerk.nl',
					'email'			=> 'carsten@vrieswerk.nl'
				),
				'description'	=> 'Allows you to add parameters, PHP evaluated or not, to Symphony\'s parameter pool.'
	 		);
		}
		
		public function uninstall() {
			$this->_Parent->Configuration->remove('globalparamloader');
			$this->_Parent->Database->query("DROP TABLE `tbl_gpl_sets`");
			$this->_Parent->Database->query("DROP TABLE `tbl_gpl_params`");
			$this->_Parent->saveConfig();
		}
		
		public function install() {
			$this->_Parent->Database->query("
				CREATE TABLE IF NOT EXISTS `tbl_gpl_sets` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`name` varchar(255) NOT NULL,
					`params` int(11) unsigned,
					`exclude_page` varchar(255),
					PRIMARY KEY (`id`)
				)
			");
			
			$this->_Parent->Database->query("
				CREATE TABLE IF NOT EXISTS `tbl_gpl_params` (
					`id` int(11) NOT NULL auto_increment,
					`set_id` int(11) NOT NULL,
					`param` varchar(255) NOT NULL,
					`value` varchar(255),
					`type` varchar(255) NOT NULL,
					`sortorder` int(11) NOT NULL,
					PRIMARY KEY (`id`)
				)
			");
			
			return true;
		}
		
		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'FrontendParamsResolve',
					'callback'	=> 'addParam'
				),
				array(
					'page'		=> '/system/sets/',
					'delegate'	=> 'AddCustomPreferenceFieldsets',
					'callback'	=> 'sets'
				)
			);
		}
		
		public function fetchNavigation() {
			return array(
				array(
					'location'	=> 200,
					'name'	=> 'Global Parameters',
					'link'	=> '/sets/'
				)
			);
		}
		
		public function addParam(&$context) {
			$sets = $this->getSets();
			
			foreach ($sets as $set) {
				if(!$this->isPageSelected($context['params']['current-page-id'], $set['id'])) {
					$parameters = $this->getParameters($set_id);
					foreach ($parameters as $parameter) {
						/*
							To do: add safe evaluation functionality.
							If the parameter can be evaluated, it is. Otherwise, the parameter is 
							added to the context without evaluation.
						*/
						$context['params'][$parameter['param']] = @eval($parameter['value']) ? eval($parameter['value']) : $parameter['value'];
					}
				}
			}
		}
			
	/*-------------------------------------------------------------------------
		Utility functions:
	-------------------------------------------------------------------------*/
		
		public function getParameters($set_id = null) {
			if (is_numeric($set_id)) {
				return $this->_Parent->Database->fetch("
					SELECT
						c.*
					FROM
						`tbl_gpl_params` AS c
					WHERE
						c.set_id = {$set_id}
					ORDER BY
						c.sortorder ASC
				");
				
			} else {
				return $this->_Parent->Database->fetch("
					SELECT
						c.*
					FROM
						`tbl_gpl_params` AS c
					ORDER BY
						c.sortorder ASC
				");
			}
		}
		
		public function getPages() {
			$pages = $this->_Parent->Database->fetch("
				SELECT
					p.*
				FROM
					`tbl_pages` AS p
				ORDER BY
					`sortorder` ASC
			");
			$result = array();
			
			foreach ($pages as $page) {
				$page = (object)$page;
				$path = '';
				
				if ($page->path) {
					$path = '/' . $page->path;
				}
				
				$path .= '/' . $page->handle;
				
				$result[] = (object)array(
					'id'	=> $page->id,
					'path'	=> $path
				);
			}
			
			sort($result);
			
			return $result;
		}
		
		public function getSets() {
			return $this->_Parent->Database->fetch("
				SELECT
					s.*
				FROM
					`tbl_gpl_sets` AS s
				ORDER BY
					s.name ASC
			");
		}
		
		public function getSet($set_id) {
			return $this->_Parent->Database->fetchRow(0, "
				SELECT
					s.*
				FROM
					`tbl_gpl_sets` AS s
				WHERE
					s.id = '{$set_id}'
				LIMIT 1
			");
		}
		
		public function getParamPages($set_id) {
			$set = $this->getSet($set_id);
			
			return explode(',', $set['exclude_page']);
		}
		
		public function isPageSelected($id, $set_id) {
			$pages = $this->getParamPages($set_id);
			
			return in_array($id, $pages);
		}		
	}
?>