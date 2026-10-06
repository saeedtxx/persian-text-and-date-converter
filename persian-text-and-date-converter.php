<?php
/*
 * Plugin Name: Persian Text and Date Converter
 * Description: به‌طور خودکار حروف عربی، اعداد و تاریخ میلادی را در محتوای وردپرس، نظرات، صفحات و عناوین به فارسی تبدیل می‌کند.
 * Version: 2.0
 * Author: Saeed Tosifian
 * Author URI: https://linkedin.com/in/saeedtx
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: persian-text-and-date-converter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تبدیل حروف عربی و اعداد فقط روی متن خام (جلوگیری از تغییر تگ‌های HTML)
 */
function ptdc_convert_text($content) {
    if (empty($content) || is_admin()) {
        return $content;
    }

    // جداسازی بخش‌های HTML تا ویژگی‌های تگ‌ها و لینک‌ها دست‌نخورده باقی بمانند
    $parts = preg_split('/(<[^>]+>)/u', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
    
    $arabic_chars  = ['ي', 'ك', 'ة'];
    $persian_chars = ['ی', 'ک', 'ه'];

    $en_numbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $fa_numbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    foreach ($parts as &$part) {
        // اگر تگ HTML یا شورت‌کد نبود، تبدیل اعمال شود
        if (!empty($part) && $part[0] !== '<') {
            $part = str_replace($arabic_chars, $persian_chars, $part);
            $part = str_replace($en_numbers, $fa_numbers, $part);
        }
    }

    return implode('', $parts);
}

/**
 * تبدیل میلادی به شمسی
 */
function ptdc_gregorian_to_jalali($gy, $gm, $gd) {
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $jy = ($gy <= 1600) ? 0 : 979;
    $gy -= ($gy <= 1600) ? 0 : 1600;
    $gy2 = ($gm > 2) ? $gy + 1 : $gy;
    $days = 365 * $gy + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100) + (int)(($gy2 + 399) / 400) - 80 + $gd + $g_d_m[$gm - 1];
    
    $jy += 33 * (int)($days / 12053);
    $days %= 12053;
    $jy += 4 * (int)($days / 1461);
    $days %= 1461;
    $jy += (int)(($days - 1) / 365);
    
    if ($days > 365) {
        $days = ($days - 1) % 365;
    }
    
    $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
    
    return [$jy, $jm, $jd];
}

/**
 * فرمت‌بندی تاریخ بر اساس استانداردهای وردپرس
 */
function ptdc_format_jalali($timestamp, $format = '') {
    if (empty($format)) {
        $format = get_option('date_format');
    }

    $gy = (int)gmdate('Y', $timestamp);
    $gm = (int)gmdate('n', $timestamp);
    $gd = (int)gmdate('j', $timestamp);

    list($jy, $jm, $jd) = ptdc_gregorian_to_jalali($gy, $gm, $gd);

    $months = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
        4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
        10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'
    ];

    $replacements = [
        'Y' => sprintf('%04d', $jy),
        'y' => substr((string)$jy, -2),
        'm' => sprintf('%02d', $jm),
        'n' => $jm,
        'F' => $months[$jm],
        'M' => $months[$jm],
        'd' => sprintf('%02d', $jd),
        'j' => $jd,
    ];

    // جایگزینی کاراکترهای فرمت
    $output = '';
    $len = strlen($format);
    for ($i = 0; $i < $len; $i++) {
        $char = $format[$i];
        if (isset($replacements[$char])) {
            $output .= $replacements[$char];
        } else {
            $output .= gmdate($char, $timestamp);
        }
    }

    return ptdc_convert_text($output);
}

// فیلترهای نمایش محتوا، عناوین و نظرات (بدون تغییر دیتابیس)
add_filter('the_content', 'ptdc_convert_text', 20);
add_filter('the_title', 'ptdc_convert_text', 20);
add_filter('comment_text', 'ptdc_convert_text', 20);

// هوک تاریخ‌های وردپرس
add_filter('wp_date', function($formatted, $format, $timestamp) {
    return ptdc_format_jalali($timestamp, $format);
}, 10, 3);

add_filter('date_i18n', function($date, $format, $timestamp) {
    return ptdc_format_jalali($timestamp, $format);
}, 10, 3);
