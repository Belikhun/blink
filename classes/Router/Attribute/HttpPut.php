<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace Blink\Router\Attribute;

use Attribute;

/**
 * The Http PUT routing attribute, designed to be used in
 * a controller.
 *
 * @package		vloom_core
 * @copyright 	2023 Videa {@link https://videabiz.com}
 * @license		http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

#[Attribute(Attribute::TARGET_METHOD)]
class HttpPut extends HttpMethod {
	const VERB = "PUT";
}
