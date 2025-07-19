<?php
/**
 * ajax_rating.php
 * Обрабатывает AJAX-запросы для рейтинга статей в базе знаний Bitrix.
 * Поддерживает два действия:
 * - getStatus: возвращает статус включённости рейтинга, среднюю оценку, личную оценку пользователя
 * - saveRating: сохраняет или обновляет оценку пользователя и возвращает обновлённую среднюю оценку
 */

define('PUBLIC_AJAX_MODE', true);
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use Bitrix\Main\Application;

Loader::includeModule('iblock');

$request = Application::getInstance()->getContext()->getRequest();
$act = $request->get('action');
$data = json_decode(file_get_contents('php://input'), true);
$code = trim($data['code'] ?? '');

// ID инфоблоков
$IB_STATUS = 17; // Инфоблок со статусом включённости рейтинга
$IB_RATING = 18; // Инфоблок с оценками

/**
 * Обработка действия получения статуса
 */
if ($act === 'getStatus') {
    $enabled = false; // включён ли рейтинг
    $my = 0;           // личная оценка пользователя
    $avg = 0;          // средняя оценка
    $cnt = 0;          // количество оценок

    // Проверяем, включён ли рейтинг для статьи
    $q = CIBlockElement::GetList([], [
        'IBLOCK_ID' => $IB_STATUS,
        'PROPERTY_ARTICLE_CODE' => $code,
        'PROPERTY_IS_RATING_ENABLED_VALUE' => 'Да'
    ], false, false, ['ID']);

    if ($q->Fetch()) {
        $enabled = true;
    }

    // Если рейтинг включён — получаем оценки
    if ($enabled) {
        global $USER;
        $uid = $USER->GetID();

        // Получаем личную оценку
        $r = CIBlockElement::GetList([], [
            'IBLOCK_ID' => $IB_RATING,
            'PROPERTY_ARTICLE_CODE' => $code,
            'PROPERTY_USER_ID' => $uid
        ], false, false, ['ID', 'PROPERTY_RATING']);

        if ($ar = $r->Fetch()) {
            $my = (int)$ar['PROPERTY_RATING_VALUE'];
        }

        // Получаем среднюю оценку
        $sum = 0;
        $cnt = 0;

        $r = CIBlockElement::GetList([], [
            'IBLOCK_ID' => $IB_RATING,
            'PROPERTY_ARTICLE_CODE' => $code
        ], false, false, ['ID', 'PROPERTY_RATING']);

        while ($a = $r->Fetch()) {
            $sum += (int)$a['PROPERTY_RATING_VALUE'];
            $cnt++;
        }

        $avg = $cnt ? round($sum / $cnt, 2) : 0;
    }

    echo json_encode([
        'enabled' => $enabled,
        'userRating' => $my,
        'avg' => $avg,
        'count' => $cnt
    ]);
    exit;
}

/**
 * Обработка действия сохранения оценки
 */
elseif ($act === 'saveRating') {
    global $USER;

    // Только для авторизованных
    if (!$USER->IsAuthorized()) exit;

    $uid = $USER->GetID();
    $rating = max(1, min(5, (int)$data['rating'])); // ограничиваем рейтинг от 1 до 5

    // Ищем существующую оценку
    $r = CIBlockElement::GetList([], [
        'IBLOCK_ID' => $IB_RATING,
        'PROPERTY_ARTICLE_CODE' => $code,
        'PROPERTY_USER_ID' => $uid
    ], false, false, ['ID']);

    if ($ar = $r->Fetch()) {
        // Обновляем существующую оценку
        CIBlockElement::SetPropertyValuesEx($ar['ID'], $IB_RATING, [
            'RATING' => $rating,
            'DATE' => date('d.m.Y H:i:s') // обновляем дату
        ]);
    } else {
        // Добавляем новую оценку
        $el = new CIBlockElement;
        $el->Add([
            'IBLOCK_ID' => $IB_RATING,
            'NAME' => "R-$code-$uid",
            'ACTIVE' => 'Y',
            'PROPERTY_VALUES' => [
                'ARTICLE_CODE' => $code,
                'USER_ID' => $uid,
                'RATING' => $rating,
                'DATE' => date('d.m.Y H:i:s') // устанавливаем дату
            ]
        ]);
    }

    // Считаем среднюю оценку
    $sum = 0;
    $cnt = 0;

    $r = CIBlockElement::GetList([], [
        'IBLOCK_ID' => $IB_RATING,
        'PROPERTY_ARTICLE_CODE' => $code
    ], false, false, ['ID', 'PROPERTY_RATING']);

    while ($a = $r->Fetch()) {
        $sum += (int)$a['PROPERTY_RATING_VALUE'];
        $cnt++;
    }

    $avg = $cnt ? round($sum / $cnt, 2) : 0;

    echo json_encode(['ok' => 1, 'avg' => $avg]);
    exit;
}

// Обработка неизвестного действия
echo json_encode(['error' => 'wrong action']);
