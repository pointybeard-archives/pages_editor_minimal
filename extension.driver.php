<?php

	Final Class extension_pages_editor_minimal extends Extension{

		public function fetchNavigation(){
			return array(
						/*array(	
							'location' => 'Blueprints',
							'name' => 'New',
							'link' => '/new/',
							//'visible' => 'no'
						),*/
						
						array(
							'location' => 'Blueprints',
							'name' => 'Edit',
							'link' => '/edit/',
							'visible' => 'no'
						),					
			);
		}		

		public function about(){
			return array('name' => 'Pages Editor - Minimal',
						 'version' => '1.0',
						 'release-date' => '2009-04-30',
						 'author' => array('name' => 'Alistair Kearney',
										   'website' => 'http://www.symphony21.com',
										   'email' => 'alistair@symphony21.com')
				 		);
		}

		public function getSubscribedDelegates(){
			return array(
										
						array(
							'page' => '/backend/',
							'delegate' => 'InitaliseAdminPageHead',
							'callback' => '__hijack'
						),		
						
			);
		}
		
		public function __hijack(array $context=NULL){
			if(preg_match('/\/symphony\/blueprints\/pages\/edit\/(\d+)\//i', Administration::instance()->getCurrentPageURL(), $match)){
				redirect(URL . '/symphony/extension/pages_editor_minimal/edit/' . $match[1] . '/');
			}
		}
		
	}

