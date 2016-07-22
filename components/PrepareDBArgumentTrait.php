<?php
/**
 * This file is part of the Trident package.
 *
 * Perederko Ruslan <perederko.ruslan@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace components;


trait PrepareDBArgumentTrait
{
    protected function prepare($value)
    {
        return stripcslashes(
            htmlspecialchars(
                str_replace(
                    "`",
                    "``",
                    trim($value)
                )
            )
        );
    }
}