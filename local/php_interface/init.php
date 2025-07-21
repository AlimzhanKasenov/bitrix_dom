<?php
use Bitrix\Main\Page\Asset;
use Bitrix\Main\Loader;

// --- ПОДКЛЮЧЕНИЕ CSS и JS для БЗ (Knowledge base) ---
AddEventHandler('main', 'OnEpilog', function () {
    $url = $_SERVER['REQUEST_URI'];
    if (strpos($url, '/knowledge/') === 0)
    {
        $asset = Asset::getInstance();
        $asset->addCss('/local/js/kb_rating/kb_rating.css');
        $asset->addJs('/local/js/kb_rating/kb_rating.js');
    }
});




Loader::registerAutoLoadClasses(null, [
    'Ac\FieldAccess\UserField\AccessField' =>
        '/local/modules/ac.fieldaccess/lib/UserField/AccessField.php',
]);

AddEventHandler(
    'main',
    'OnUserTypeBuildList',
    ['Ac\FieldAccess\UserField\AccessField', 'getDescription'],
    5000
);

AddEventHandler('main', 'OnUserTypeRightsCheck',
    static function (&$result, array $userField, ?int $userId, string $permission)
    {
        if ($userField['USER_TYPE_ID'] === \Ac\FieldAccess\UserField\AccessField::USER_TYPE_ID)
        {
            $result['RESULT'] = \Ac\FieldAccess\UserField\AccessField::checkAccess($userField, $userId, $permission);
        }
    }
);