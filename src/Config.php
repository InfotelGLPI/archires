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

namespace GlpiPlugin\Archires;

use CommonDBTM;
use Html;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class Config extends CommonDBTM
{

   static $rightname = 'plugin_archires';

    /**
     * Get name of this type by language of the user connected
     *
     * @param integer $nb number of elements
     * @return string name of this type
     */
    static function getTypeName($nb = 0)
    {
        return __('Network architecture', 'archires');
    }

    function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        return $ong;
    }

    function showForm($ID, $options = [])
    {
        if (!$this->canView()) {
            return false;
        }
        if (empty($ID)) {
            $this->getEmpty();
        } else {
            $this->getFromDB($ID);
        }


        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        echo Html::hidden('id', ['value' => 1]);

        $this->showFormButtons(['candel' => false]);
        Html::closeForm();
    }
}
