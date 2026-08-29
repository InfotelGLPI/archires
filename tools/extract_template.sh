#!/bin/bash

#
# -------------------------------------------------------------------------
# archires plugin for GLPI
# -------------------------------------------------------------------------
#
# LICENSE
#
# This file is part of archires.
#
# archires is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 3 of the License, or
# (at your option) any later version.
#
# archires is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with archires. If not, see <http://www.gnu.org/licenses/>.
# -------------------------------------------------------------------------
# @copyright Copyright (C) 2009-2026 by archires plugin team.
# @license   AGPLv3 https://www.gnu.org/licenses/agpl-3.0.html
# @link      https://github.com/InfotelGLPI/archires
# --------------------------------------------------------------------------
#

# Only strings with domain specified are extracted (use Xt args of keyword param to set number of args needed)

xgettext *.php */*.php --copyright-holder='Archires Development Team' --package-name='GLPI - Archires plugin' --package-version='1.0.0' -o locales/glpi.pot -L PHP --add-comments=TRANS --from-code=UTF-8 --force-po  \
	--keyword=_n:1,2,4t --keyword=__s:1,2t --keyword=__:1,2t --keyword=_e:1,2t --keyword=_x:1c,2,3t \
	--keyword=_ex:1c,2,3t --keyword=_nx:1c,2,3,5t --keyword=_sx:1c,2,3t \
	`# php-cs-fixer adds a trailing comma to every multiline call, and xgettext counts it as` \
	`# one extra argument, so the specs above stop matching and strings are silently dropped.` \
	`# These duplicates accept the same calls with that extra argument. Keep both lists in sync.` \
	--keyword=_n:1,2,5t --keyword=__s:1,3t --keyword=__:1,3t --keyword=_e:1,3t --keyword=_x:1c,2,4t \
	--keyword=_ex:1c,2,4t --keyword=_nx:1c,2,3,6t --keyword=_sx:1c,2,4t



