<?php
/**
 * Построение иерархии разделов и элементов для дерева в main.ui.grid.
 * @author Kassenov Alimzhan
 * @copyright (c) Kassenov Alimzhan, 19.07.2025
 *
 * Формирует $arResult['GRID_ROWS'] для вывода секций (с раскрытием по плюсикам)
 * и вложенных элементов инфоблока, без дублирования, с поддержкой ajax-раскрытия.
 */

if (!defined('B_PROLOG_INCLUDED') || $arParams['IBLOCK_ID'] <= 0) {
    return;
}

use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\Grid\Options as GridOptions;

/**
 * Получаем настройки грида (нужно для правильного раскрытия веток).
 * GRID_ID формируется по ID инфоблока.
 */
$gridId      = 'iblock_grid_' . $arParams['IBLOCK_ID'];
$gridOptions = new GridOptions($gridId);
$expanded    = $gridOptions->getExpandedRows();

/**
 * Получаем разделы инфоблока.
 * $sections — ассоциативный массив разделов, где ключ = ID раздела.
 */
$sections = [];
$sectionRes = SectionTable::getList([
    'filter' => [
        '=IBLOCK_ID' => $arParams['IBLOCK_ID'],
        '=ACTIVE' => 'Y'
    ],
    'order' => [
        'SORT' => 'ASC',
        'ID' => 'ASC'
    ],
    'select' => ['ID', 'NAME']
]);
while ($sec = $sectionRes->fetch()) {
    $sections[$sec['ID']] = $sec;
}

/**
 * Получаем элементы инфоблока и группируем их по разделам.
 * $itemsBySection — элементы сгруппированы по IBLOCK_SECTION_ID.
 */
$itemsBySection = [];
$elementRes = ElementTable::getList([
    'filter' => [
        '=IBLOCK_ID' => $arParams['IBLOCK_ID'],
        '=ACTIVE' => 'Y'
    ],
    'order' => [
        'SORT' => 'ASC',
        'ID' => 'ASC'
    ],
    'select' => ['ID', 'NAME', 'IBLOCK_SECTION_ID']
]);
while ($el = $elementRes->fetch()) {
    $itemsBySection[$el['IBLOCK_SECTION_ID']][] = $el;
}

/**
 * Формируем массив строк для грида.
 * Каждый раздел — отдельная строка с has_child=true, collapsed — свернут/развернут.
 * Вложенные элементы добавляются только если раздел раскрыт.
 */
$rows = [];

foreach ($sections as $sec) {
    $parentId = 'section_' . $sec['ID'];
    $hasChild = !empty($itemsBySection[$sec['ID']]);

    // Строка раздела (раздел верхнего уровня)
    $rows[] = [
        'id'        => $parentId,
        'data'      => [
            'ID'   => $sec['ID'],
            'NAME' => $sec['NAME'],
        ],
        'columns'   => [ // что выводится в ячейках
            'ID'   => $sec['ID'],
            'NAME' => '<b>' . htmlspecialcharsbx($sec['NAME']) . '</b>',
        ],
        'has_child' => $hasChild,
        'collapsed' => !in_array($parentId, $expanded, true), // свернут по умолчанию
        'depth'     => 0,
    ];

    // Вложенные элементы (только если раздел раскрыт)
    if ($hasChild && in_array($parentId, $expanded, true)) {
        foreach ($itemsBySection[$sec['ID']] as $item) {
            $rows[] = [
                'id'        => 'element_' . $item['ID'],
                'parent_id' => $parentId, // связываем с родителем
                'data'      => [
                    'ID'   => $item['ID'],
                    'NAME' => $item['NAME'],
                ],
                'columns'   => [
                    'ID'   => $item['ID'],
                    'NAME' => htmlspecialcharsbx($item['NAME']),
                ],
                'depth'     => 1,
            ];
        }
    }
}

// Записываем в результат для шаблона
$arResult['GRID_ROWS'] = $rows;
