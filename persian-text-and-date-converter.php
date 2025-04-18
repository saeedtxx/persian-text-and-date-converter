<?php
/*
 * Plugin Name: Persian Text and Date Converter
 * Description: به‌طور خودکار حروف عربی، اعداد و تاریخ میلادی را در محتوای وردپرس، نظرات، صفحات، آرشیوها و موارد دیگر به فارسی تبدیل می‌کند
 * Version: 1.4
 * Author: Saeed Tosifian
 * Author URI: https://linkedin.com/in/saeedtx
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: persian-text-and-date-converter
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

function ptdc_convert_arabic_to_persian($text) {
    $arabic = array('ي', 'ك', 'ة', 'ؤ', 'ئ', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
    $persian = array('ی', 'ک', 'ه', 'و', 'ی', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
    $text = str_replace($arabic, $persian, $text);

    $text = preg_replace_callback(
        '/(\d{4})-(\d{2})-(\d{2})/',
        function ($matches) {
            return ptdc_gregorian_to_jalali($matches[0]);
        },
        $text
    );

    return $text;
}

function ptdc_gregorian_to_jalali($date) {
    if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $date, $matches)) {
        $gy = (int)$matches[1];
        $gm = (int)$matches[2];
        $gd = (int)$matches[3];

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
        if ($days > 365) $days = ($days - 1) % 365;
        $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
        return sprintf('%04d-%02d-%02d', $jy, $jm, $jd);
    }
    return $date;
}

function ptdc_format_jalali_date($jalali_date) {
    $months = array(
        '01' => 'فروردین', '02' => 'اردیبهشت', '03' => 'خرداد',
        '04' => 'تیر', '05' => 'مرداد', '06' => 'شهریور',
        '07' => 'مهر', '08' => 'آبان', '09' => 'آذر',
        '10' => 'دی', '11' => 'بهمن', '12' => 'اسفند'
    );
    $parts = explode('-', $jalali_date);
    $year = $parts[0];
    $month = $parts[1];
    $day = ltrim($parts[2], '0');
    return "$day {$months[$month]} $year";
}

// تبدیل تاریخ موقع ذخیره محتوا
add_filter('content_save_pre', 'ptdc_convert_arabic_to_persian', 10, 1);

// تبدیل تاریخ نوشته‌ها و برگه‌ها
add_filter('get_the_date', 'ptdc_convert_date_to_jalali_raw', 10, 3);
add_filter('the_date', 'ptdc_convert_date_to_jalali_raw', 10, 4);

function ptdc_convert_date_to_jalali_raw($date, $format = '', $post = null) {
    if (!$post) {
        $post = get_post();
    }
    if (!$post) return $date;

    $timestamp = strtotime($post->post_date);
    $gregorian_date = gmdate('Y-m-d', $timestamp);
    $jalali_date = ptdc_gregorian_to_jalali($gregorian_date);

    if ($format) {
        $jalali_parts = explode('-', $jalali_date);
        $jy = $jalali_parts[0];
        $jm = $jalali_parts[1];
        $jd = $jalali_parts[2];
        $format = str_replace(['Y', 'm', 'd'], [$jy, $jm, $jd], $format);
        return $format;
    }

    return ptdc_format_jalali_date($jalali_date);
}

// تبدیل تاریخ دیدگاه‌ها
add_filter('get_comment_date', 'ptdc_convert_comment_date_to_jalali', 10, 3);

function ptdc_convert_comment_date_to_jalali($date, $format = '', $comment = null) {
    if (!$comment) {
        global $comment;
    }
    if (!$comment) return $date;

    $timestamp = strtotime($comment->comment_date);
    $gregorian_date = gmdate('Y-m-d', $timestamp);
    $jalali_date = ptdc_gregorian_to_jalali($gregorian_date);

    if ($format) {
        $jalali_parts = explode('-', $jalali_date);
        $jy = $jalali_parts[0];
        $jm = $jalali_parts[1];
        $jd = $jalali_parts[2];
        $format = str_replace(['Y', 'm', 'd'], [$jy, $jm, $jd], $format);
        return $format;
    }

    return ptdc_format_jalali_date($jalali_date);
}

// تبدیل تاریخ بایگانی
add_filter('get_the_archive_title', 'ptdc_convert_archive_title');
function ptdc_convert_archive_title($title) {
    if (preg_match('/(\d{4})/', $title, $matches)) {
        $year = $matches[1];
        $jalali_year = ptdc_gregorian_to_jalali("$year-01-01");
        $jalali_year = explode('-', $jalali_year)[0];
        $title = str_replace($year, $jalali_year, $title);
    }
    return $title;
}

// تبدیل تاریخ‌های محلی‌سازی‌شده
add_filter('date_i18n', 'ptdc_convert_date_i18n', 10, 4);
function ptdc_convert_date_i18n($date, $format, $timestamp, $gmt) {
    $gregorian_date = gmdate('Y-m-d', $timestamp);
    $jalali_date = ptdc_gregorian_to_jalali($gregorian_date);
    return ptdc_format_jalali_date($jalali_date);
}

// تبدیل پست‌های قدیمی موقع فعال‌سازی
register_activation_hook(__FILE__, 'ptdc_convert_existing_posts');
function ptdc_convert_existing_posts() {
    if (get_option('ptdc_converted_posts') === 'done') {
        return;
    }
    $posts = get_posts(array('numberposts' => -1));
    foreach ($posts as $post) {
        $new_content = ptdc_convert_arabic_to_persian($post->post_content);
        wp_update_post(array('ID' => $post->ID, 'post_content' => $new_content));
    }
    update_option('ptdc_converted_posts', 'done');
}