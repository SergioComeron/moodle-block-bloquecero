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

namespace block_bloquecero;

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the bloquecero header course title.
 *
 * @package    block_bloquecero
 * @category   test
 * @copyright  2026 Sergio Comerón <info@sergiocomeron.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(course_name::class)]
final class course_name_test extends advanced_testcase {
    /**
     * @return array<string, array{string, string}>
     */
    public static function fullname_provider(): array {
        return [
            'laboratorio in second segment' => [
                'Expresión Gráfica - 110.Laboratorio - Segundo Semestre_SINC',
                'Expresión Gráfica · Laboratorio',
            ],
            'no laboratorio' => [
                'Expresión Gráfica - 110 - Segundo Semestre_SINC',
                'Expresión Gráfica',
            ],
            'already in first segment' => [
                'Laboratorio de Química - 110 - Segundo Semestre_SINC',
                'Laboratorio de Química',
            ],
            'no separator' => [
                'Expresión Gráfica',
                'Expresión Gráfica',
            ],
        ];
    }

    /**
     * Header title keeps the short name and restores Laboratorio when truncated.
     */
    #[DataProvider('fullname_provider')]
    public function test_display(string $fullname, string $expected): void {
        $this->assertSame($expected, course_name::display($fullname));
    }
}
