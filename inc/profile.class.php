<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 archires plugin for GLPI
 Copyright (C) 2009-2017 by the archires Development Team.

 https://github.com/InfotelGLPI/archires
 -------------------------------------------------------------------------

 LICENSE

 This file is part of archires.

 archires is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 archires is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with archires. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginarchiresProfile
 */
class PluginArchiresProfile extends Profile {

   static $rightname = "profile";

   /**
    * @param CommonGLPI $item
    * @param int        $withtemplate
    *
    * @return string|translated
    */
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if ($item->getType() == 'Profile' && $item->getField('interface') != 'helpdesk'
      ) {
         return __('Network architecture', 'archires');
      }
      return '';
   }

   /**
    * @param CommonGLPI $item
    * @param int        $tabnum
    * @param int        $withtemplate
    *
    * @return bool
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      if ($item->getType() == 'Profile') {
         $ID   = $item->getID();
         $prof = new self();
          self::addDefaultProfileInfos(
              $ID,
              ['plugin_archires'               => 0]
          );

         $prof->showForm($ID);
      }
      return true;
   }

   /**
    * @param $ID
    */
   static function createFirstAccess($ID) {
      //85
       self::addDefaultProfileInfos(
           $ID,
           ['plugin_archires'               => 127],
           true
       );
   }

   /**
    * @param      $profiles_id
    * @param      $rights
    * @param bool $drop_existing
    *
    * @internal param $profile
    */
   static function addDefaultProfileInfos($profiles_id, $rights, $drop_existing = false) {

      $profileRight = new ProfileRight();
      $dbu          = new DbUtils();
      foreach ($rights as $right => $value) {
         if ($dbu->countElementsInTable('glpi_profilerights',
                                  ["profiles_id" => $profiles_id, "name" => $right]) && $drop_existing) {
            $profileRight->deleteByCriteria(['profiles_id' => $profiles_id, 'name' => $right]);
         }
         if (!$dbu->countElementsInTable('glpi_profilerights',
                                   ["profiles_id" => $profiles_id, "name" => $right])) {
            $myright['profiles_id'] = $profiles_id;
            $myright['name']        = $right;
            $myright['rights']      = $value;
            $profileRight->add($myright);

            //Add right to the current session
            $_SESSION['glpiactiveprofile'][$right] = $value;
         }
      }
   }

   /**
    * Show profile form
    *
    * @param int  $profiles_id
    * @param bool $openform
    * @param bool $closeform
    *
    * @return nothing
    * @internal param int $items_id id of the profile
    * @internal param value $target url of target
    */
   function showForm($profiles_id = 0, $openform = TRUE, $closeform = TRUE) {

      echo "<div class='firstbloc'>";
      if (($canedit = Session::haveRightsOr(self::$rightname, array(CREATE, UPDATE, PURGE))) && $openform) {
         $profile = new Profile();
         echo "<form method='post' action='" . $profile->getFormURL() . "'>";
      }

      $profile = new Profile();
      $profile->getFromDB($profiles_id);
      if ($profile->getField('interface') == 'central') {
         $rights = $this->getAllRights();
         $profile->displayRightsChoiceMatrix($rights, array('canedit'       => $canedit,
                                                            'default_class' => 'tab_bg_2',
                                                            'title'         => __('General')));
      }

      if ($canedit && $closeform) {
         echo "<div class='center'>";
         echo Html::hidden('id', array('value' => $profiles_id));
         echo Html::submit(_sx('button', 'Save'), array('name' => 'update'));
         echo "</div>\n";
         Html::closeForm();
      }
      echo "</div>";
   }

   /**
    * @param bool $all
    *
    * @return array
    */
   static function getAllRights($all = false) {
      $rights = array(
         array('itemtype' => 'PluginArchiresImpactcontext',
               'label'    => PluginArchiresImpactcontext::getTypeName(2),
               'field'    => 'plugin_archires'
         ),
      );

      return $rights;
   }

   /**
    * Init profiles
    *
    * @param $old_right
    *
    * @return int
    */
   static function translateARight($old_right) {
      switch ($old_right) {
         case '':
            return 0;
         case 'r' :
            return READ;
         case 'w':
            return ALLSTANDARDRIGHT;
         case '0':
         case '1':
            return $old_right;

         default :
            return 0;
      }
   }

   /**
    * Initialize profiles, and migrate it necessary
    */
   static function initProfile() {
      global $DB;
      $profile = new self();
      $dbu     = new DbUtils();

      //Add new rights in glpi_profilerights table
      foreach ($profile->getAllRights(true) as $data) {
         if ($dbu->countElementsInTable("glpi_profilerights", ["name" => $data['field']]) == 0) {
            ProfileRight::addProfileRights([$data['field']]);
         }
      }

       $it = $DB->request([
           'FROM' => 'glpi_profilerights',
           'WHERE' => [
               'profiles_id' => $_SESSION['glpiactiveprofile']['id'],
               'name' => ['LIKE', '%plugin_archires%']
           ]
       ]);
       foreach ($it as $prof) {
         $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights'];
      }
   }


   static function removeRightsFromSession() {
      foreach (self::getAllRights(true) as $right) {
         if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
            unset($_SESSION['glpiactiveprofile'][$right['field']]);
         }
      }
   }
}
