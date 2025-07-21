<?php
namespace Ac\FieldAccess\UserField;

use Bitrix\Main\UserField\Types\BaseType;

/**
 * Кастомный тип пользовательского поля с гибким управлением доступом.
 * Позволяет ограничивать доступ по группам, отдельным пользователям или пользователям, указанным прямо в поле.
 *
 * @author Alimzhan Kassenov
 * @date 2025-07-21
 */
class AccessField extends BaseType
{
    /** @var string Идентификатор типа поля */
    public const USER_TYPE_ID = 'accessfield';

    /**
     * Возвращает описание типа для Bitrix.
     *
     * @return array
     */
    public static function getDescription(): array
    {
        return [
            'USER_TYPE_ID'        => static::USER_TYPE_ID,
            'CLASS_NAME'          => __CLASS__,
            'DESCRIPTION'         => 'Поле с гибкими правами доступа',
            'BASE_TYPE'           => 'string',
            'USE_FIELD_COMPONENT' => false,
            'EDIT_CALLBACK'       => [__CLASS__, 'getPublicEdit'],
            'VIEW_CALLBACK'       => [__CLASS__, 'getPublicView'],
        ];
    }

    /**
     * Возвращает тип колонки в БД для этого поля.
     *
     * @return string
     */
    public static function getDbColumnType(): string
    {
        return 'text';
    }

    /**
     * Сохраняет настройки поля.
     *
     * @param array $userField
     * @return array
     */
    public static function prepareSettings(array $userField): array
    {
        $req = $_REQUEST['FIELDS']['SETTINGS'] ?? [];
        $set['ACCESS_SOURCE'] = $req['ACCESS_SOURCE'] ?? 'GROUPS';

        if ($set['ACCESS_SOURCE'] === 'GROUPS') {
            $grp = (array)($req['GROUPS'] ?? []);
            $set['GROUPS'] = array_map('intval', $grp) ?: [1];
        }

        if ($set['ACCESS_SOURCE'] === 'USERS') {
            $raw = $req['USERS'] ?? '';
            $ids = preg_split('/\s*,\s*/', (string)$raw, -1, PREG_SPLIT_NO_EMPTY);
            $set['USERS'] = array_filter(array_map('intval', $ids));
        }

        // IN_FIELD_USERS не требует сохранения, всё хранится в VALUE

        return $set;
    }

    /**
     * Проверяет доступ пользователя к значению поля.
     *
     * @param array $field UF_* массив поля
     * @param int $userId ID пользователя (по умолчанию текущий)
     * @param string $perm Тип доступа ('view' или 'write')
     * @return bool
     */
    public static function checkAccess(array $field, int $userId = 0, string $perm = 'view'): bool
    {
        global $USER;
        $userId = $userId ?: (is_object($USER) ? (int)$USER->GetID() : 0);

        $settings = $field['SETTINGS'] ?? [];
        $source = $settings['ACCESS_SOURCE'] ?? 'GROUPS';
        $result = false;
        $allowed = [];
        $value = $field['VALUE'] ?? '';

        switch ($source) {
            case 'GROUPS':
                $allowed = $settings['GROUPS'] ?? [];
                $result = (bool)array_intersect(self::getUserGroups($userId), $allowed);
                break;
            case 'USERS':
                $allowed = $settings['USERS'] ?? [];
                $result = in_array($userId, $allowed, true);
                break;
            case 'IN_FIELD_USERS':
                $arr = self::parseValue($value);
                $allowed = $arr['USERS'] ?? [];
                $result = in_array($userId, $allowed, true);
                break;
        }

        // Админ всегда видит и может всё
        if (is_object($USER) && $USER->IsAdmin()) {
            $result = true;
            $source = 'admin';
        }

        // // Уведомление админу — включить при необходимости
        // if ($userId != 1) {
        //     self::notifyAdmin([
        //         'uid'    => $userId,
        //         'source' => $source,
        //         'allowed'=> $allowed,
        //         'value'  => $value,
        //         'result' => $result ? 'true' : 'false',
        //         'perm'   => $perm,
        //     ]);
        // }

        return $result;
    }

    /**
     * Вывод значения поля в публичной части (view).
     * Если нет доступа — показывает красный текст.
     *
     * @param array $uf
     * @param array|null $add
     * @return string
     */
    public static function getPublicView(array $uf, ?array $add = []): string
    {
        // -- исправление: если нет доступа, ВСЕГДА выводим предупреждение
        if (!self::checkAccess($uf)) {
            return '<span style="color:#c00;">Недостаточно прав для просмотра</span>';
        }

        $settings = $uf['SETTINGS'] ?? [];
        if (($settings['ACCESS_SOURCE'] ?? '') === 'IN_FIELD_USERS') {
            $arr = self::parseValue($uf['VALUE'] ?? '');
            return htmlspecialcharsbx($arr['VALUE'] !== '' ? $arr['VALUE'] : (string)$uf['VALUE']);
        }

        return htmlspecialcharsbx((string)($uf['VALUE'] ?? ''));
    }

    /**
     * Отрисовывает HTML для публичного редактирования поля.
     *
     * @param array $uf
     * @param array|null $add
     * @return string
     */
    public static function getPublicEdit(array $uf, ?array $add = []): string
    {
        if (!self::checkAccess($uf, 0, 'write')) {
            return '<span style="color:#c00;">Недостаточно прав</span>';
        }

        $settings = $uf['SETTINGS'] ?? [];
        $name = htmlspecialcharsbx($uf['FIELD_NAME']);

        if (($settings['ACCESS_SOURCE'] ?? '') === 'IN_FIELD_USERS') {
            $arr = self::parseValue($uf['VALUE'] ?? '');
            $val = htmlspecialcharsbx($arr['VALUE'] ?? '');
            $users = htmlspecialcharsbx(implode(',', $arr['USERS'] ?? []));
            return "<input type='text' name='{$name}[VALUE]' value='{$val}' style='width:45%'>"
                . " <input type='text' name='{$name}[USERS]' value='{$users}' style='width:35%' placeholder='ID через запятую' title='ID пользователей с доступом'>";
        } else {
            $val = htmlspecialcharsbx((string)($uf['VALUE'] ?? ''));
            return "<input type='text' name='{$name}' value='{$val}' style='width:70%'>";
        }
    }

    /**
     * HTML для настроек пользовательского поля (отображается в админке).
     *
     * @param array $userField
     * @param array|null $additionalParameters
     * @param mixed $varsFromForm
     * @return string
     */
    public static function getSettingsHtml($userField, ?array $additionalParameters, $varsFromForm): string
    {
        $settings     = $userField['SETTINGS'] ?? [];
        $source       = $settings['ACCESS_SOURCE'] ?? 'GROUPS';

        $selGroups = [];
        if (!empty($settings['GROUPS'])) {
            $selGroups = is_array($settings['GROUPS'])
                ? $settings['GROUPS']
                : preg_split('/\s*,\s*/', (string)$settings['GROUPS'], -1, PREG_SPLIT_NO_EMPTY);
            $selGroups = array_map('intval', $selGroups);
        }

        $selUsers = '';
        if (!empty($settings['USERS'])) {
            $selUsers = is_array($settings['USERS'])
                ? implode(',', $settings['USERS'])
                : $settings['USERS'];
        }

        $namePrefix = 'FIELDS[SETTINGS]';

        $html  = '<div style="margin-bottom:8px">';
        $html .= '<label><input type="radio" name="'.$namePrefix.'[ACCESS_SOURCE]" value="GROUPS"'
            .($source=='GROUPS' ? ' checked':'').'> Группы</label> ';
        $html .= '<label><input type="radio" name="'.$namePrefix.'[ACCESS_SOURCE]" value="USERS"'
            .($source=='USERS' ? ' checked':'').'> Пользователи</label> ';

        $html .= '<div id="af-groups" style="'.($source!='GROUPS'?'display:none':'').'">';
        $rs = \CGroup::GetList($by='c_sort', $order='asc', []);
        while ($ar = $rs->Fetch())
        {
            if ($ar['ID'] < 2) continue;
            $chk = in_array((int)$ar['ID'], $selGroups, true) ? 'checked':'';
            $html .= '<label style="display:inline-block;margin:2px 8px 2px 0">
                <input type="checkbox" name="'.$namePrefix.'[GROUPS][]" value="'.$ar['ID'].'" '.$chk.'>
                '.htmlspecialcharsbx($ar['NAME']).'
              </label>';
        }
        $html .= '</div>';

        $html .= '<div id="af-users" style="margin-top:6px;'.($source!='USERS'?'display:none':'').'">
            <input type="text" size="40" name="'.$namePrefix.'[USERS]" value="'.htmlspecialcharsbx($selUsers).'"
                   placeholder="ID через запятую">
          </div>';

        $html .= '<div id="af-infield" style="margin-top:6px;'.($source!='IN_FIELD_USERS'?'display:none':'').'">
            <em>Права на просмотр и редактирование будут задаваться прямо в значении поля (ID пользователей, разделённых запятыми).</em>
          </div>';

        $html .= "<script>
BX.ready(function(){
    let radios = document.getElementsByName('{$namePrefix}[ACCESS_SOURCE]');
    Array.from(radios).forEach(r=>{
        r.onclick = function(){
            BX('af-groups').style.display = (this.value==='GROUPS') ? 'block' : 'none';
            BX('af-users').style.display  = (this.value==='USERS')  ? 'block' : 'none';
            BX('af-infield').style.display  = (this.value==='IN_FIELD_USERS')  ? 'block' : 'none';
        };
    });
});
</script>";

        return $html;
    }

    /**
     * Получает список групп пользователя по ID.
     *
     * @param int $userId
     * @return array
     */
    protected static function getUserGroups(int $userId): array
    {
        static $cache = [];
        if (!isset($cache[$userId])) {
            $cache[$userId] = array_map('intval', \CUser::GetUserGroup($userId));
        }
        return $cache[$userId];
    }

    /**
     * Парсит значение поля в режиме IN_FIELD_USERS (json или массив).
     *
     * @param mixed $val
     * @return array{VALUE: string, USERS: array}
     */
    protected static function parseValue($val)
    {
        if (is_array($val)) {
            if (isset($val['VALUE']) && isset($val['USERS'])) {
                $users = is_array($val['USERS']) ? $val['USERS'] : preg_split('/\s*,\s*/', (string)$val['USERS'], -1, PREG_SPLIT_NO_EMPTY);
                return [
                    'VALUE' => $val['VALUE'],
                    'USERS' => array_filter(array_map('intval', $users)),
                ];
            }
        }
        if (is_string($val) && $val !== '') {
            $arr = @json_decode($val, true);
            if (is_array($arr) && isset($arr['VALUE']) && isset($arr['USERS'])) {
                return [
                    'VALUE' => $arr['VALUE'],
                    'USERS' => array_filter(array_map('intval', (array)$arr['USERS'])),
                ];
            }
        }
        return ['VALUE'=>'', 'USERS'=>[]];
    }

    /**
     * Сохраняет значение поля (приведение к json, если надо).
     *
     * @param array $arUserField
     * @param mixed $value
     * @return string|mixed
     */
    public static function onBeforeSave($arUserField, $value)
    {
        $settings = $arUserField['SETTINGS'] ?? [];
        if (($settings['ACCESS_SOURCE'] ?? '') === 'IN_FIELD_USERS') {
            if (is_string($value) && @json_decode($value, true)) {
                return $value;
            }
            if (is_array($value) && isset($value['VALUE'])) {
                return json_encode([
                    'VALUE' => $value['VALUE'],
                    'USERS' => array_filter(array_map('intval',
                        is_array($value['USERS']) ? $value['USERS'] : preg_split('/\s*,\s*/', (string)$value['USERS'], -1, PREG_SPLIT_NO_EMPTY)
                    )),
                ]);
            }
            return json_encode([
                'VALUE' => $value,
                'USERS' => [],
            ]);
        }
        return $value;
    }

    /**
     * (Опционально) отправка уведомления админу в колокольчик Bitrix и запись в Event-Log.
     *
     * @param array $data
     */
    /*
    protected static function notifyAdmin($data): void
    {
        $adminId = 1;
        $msg = "AccessField: checkAccess\n<pre style=\"font-size:13px;\">"
            . print_r($data, true) . "</pre>";

        \CEventLog::Add([
            'SEVERITY'      => 'DEBUG',
            'AUDIT_TYPE_ID' => 'ACCESSFIELD_DEBUG',
            'MODULE_ID'     => 'main',
            'DESCRIPTION'   => $msg,
        ]);

        if (\Bitrix\Main\Loader::includeModule('im')) {
            \CIMNotify::Add([
                'TO_USER_ID'      => $adminId,
                'FROM_USER_ID'    => 0,
                'NOTIFY_TYPE'     => IM_NOTIFY_SYSTEM,
                'NOTIFY_MODULE'   => 'main',
                'NOTIFY_TAG'      => 'accessfield_'.md5($msg),
                'NOTIFY_MESSAGE'  => $msg,
            ]);
        }
    }
    */
}
