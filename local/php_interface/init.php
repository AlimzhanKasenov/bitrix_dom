<?php

use Bitrix\Main\Page\Asset;
use Bitrix\Main\Loader;

/**
 * Обработчик для подключения CSS и JS файлов только на страницах Базы знаний (Knowledge base).
 */
AddEventHandler('main', 'OnEpilog', function () {
    $url = $_SERVER['REQUEST_URI'];
    // Проверяем, находимся ли мы на странице /knowledge/
    if (strpos($url, '/knowledge/') === 0)
    {
        $asset = Asset::getInstance();
        // Подключаем кастомный CSS для рейтинга БЗ
        $asset->addCss('/local/js/kb_rating/kb_rating.css');
        // Подключаем кастомный JS для рейтинга БЗ
        $asset->addJs('/local/js/kb_rating/kb_rating.js');
    }
});

/**
 * Регистрируем автозагрузку класса кастомного пользовательского поля "Поле с гибкими правами доступа".
 */
Loader::registerAutoLoadClasses(null, [
    'Ac\FieldAccess\UserField\AccessField' =>
        '/local/modules/ac.fieldaccess/lib/UserField/AccessField.php',
]);

/**
 * Регистрируем описание нового типа пользовательского поля в системе.
 * @see \Ac\FieldAccess\UserField\AccessField::getDescription()
 */
AddEventHandler(
    'main',
    'OnUserTypeBuildList',
    ['Ac\FieldAccess\UserField\AccessField', 'getDescription'],
    5000
);

/**
 * Обработчик проверки прав на пользовательские поля.
 * Если поле нашего типа — используем кастомную логику checkAccess().
 *
 * @param array $result     Ссылка на массив результата (модифицируется).
 * @param array $userField  Массив данных о пользовательском поле.
 * @param int|null $userId  ID пользователя, для которого проверяются права.
 * @param string $permission Тип права (например, 'view' или 'write').
 */
AddEventHandler('main', 'OnUserTypeRightsCheck',
    static function (&$result, array $userField, ?int $userId, string $permission)
    {
        if ($userField['USER_TYPE_ID'] === \Ac\FieldAccess\UserField\AccessField::USER_TYPE_ID)
        {
            $result['RESULT'] = \Ac\FieldAccess\UserField\AccessField::checkAccess($userField, $userId, $permission);
        }
    }
);
