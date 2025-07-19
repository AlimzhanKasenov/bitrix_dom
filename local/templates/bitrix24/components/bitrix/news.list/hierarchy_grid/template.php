<?php
/**
 * Шаблон вывода грида с секциями и вложенными элементами в виде collapsible-rows.
 * Используется Bitrix main.ui.grid.
 * @author Kassenov Alimzhan
 * @copyright (c) Kassenov Alimzhan, 19.07.2025
 */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\UI\Extension;

// Подключение UI-библиотек
CJSCore::Init(['ui', 'ui.grid']);
Extension::load('ui.grid');

$gridId = 'iblock_grid_' . $arParams['IBLOCK_ID'];
?>

<div style="margin-top:1rem">
    <?php
    /**
     * Подключаем компонент Bitrix main.ui.grid
     * - GRID_ID             — уникальный id для работы с состояниями (раскрыто/свернуто)
     * - COLUMNS             — список колонок
     * - ROWS                — массив строк из result_modifier.php
     * - ENABLE_COLLAPSIBLE_ROWS — включаем древовидный режим (плюсики)
     * - Все "SHOW_*" опции выключены для лаконичного вида
     * - AJAX_MODE           — включен, чтобы грид работал без перезагрузки
     */
    $APPLICATION->IncludeComponent(
        'bitrix:main.ui.grid',
        '',
        [
            'GRID_ID'                 => $gridId,
            // Колонки грида (id совпадает с ключами из columns[] в result_modifier.php)
            'COLUMNS'                 => [
                ['id' => 'ID',   'name' => 'ID',       'default' => true],
                ['id' => 'NAME', 'name' => 'Название', 'default' => true, 'shift' => true],
            ],
            // Основные строки грида (секции и элементы)
            'ROWS'                    => $arResult['GRID_ROWS'],
            // Включить раскрывающиеся строки (дерево)
            'ENABLE_COLLAPSIBLE_ROWS' => true,

            // Выключаем все лишние фичи интерфейса
            'SHOW_ROW_CHECKBOXES'     => false,
            'SHOW_GRID_SETTINGS_MENU' => false,
            'SHOW_NAVIGATION_PANEL'   => false,
            'SHOW_PAGINATION'         => false,
            'SHOW_TOTAL_COUNTER'      => false,
            'SHOW_PAGESIZE'           => false,
            'SHOW_ACTION_PANEL'       => false,

            // AJAX-настройки
            'AJAX_MODE'               => 'Y',
            'AJAX_ID'                 => \CAjax::getComponentID('bitrix:main.ui.grid', '.default', ''),
        ],
        $component,
        ['HIDE_ICONS' => 'Y']
    );
    ?>
</div>