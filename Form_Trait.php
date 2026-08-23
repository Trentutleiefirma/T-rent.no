<?php

namespace REDQ_RnB\Traits;

use Carbon\Carbon;

/**
 * Handle rental data
 */
trait Form_Trait
{
}

// T-Rent calendar-day compatibility fix.
// Loaded here because this trait is included by the RnB plugin before
// CartHandler registers its WooCommerce validation callbacks.
$trent_day_fix = dirname(__FILE__) . '/t-rent-calendar-day-fix.php';
if (file_exists($trent_day_fix)) {
    require_once $trent_day_fix;
}
