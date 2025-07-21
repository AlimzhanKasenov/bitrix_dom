<?php
namespace Ac\FieldAccess\UserField;

use Bitrix\Main\UserField\Types\BaseType;
use CUser;

/**
 * Кастомный тип пользовательского поля "Поле с гибкими правами доступа"
 * Работает для CRM, задач и пользователей. Управляет доступом на чтение/редактирование
 * по группам, по списку пользователей или по значениям прямо в поле.
 *
 * @author Alimzhan Kassenov
 * @date 2025-07-21
 */
class AccessField extends BaseType
{
    /** @var string Уникальный идентификатор типа */
    public const USER_TYPE_ID = 'accessfield';

    /**
     * Описание типа для Битрикс
     * @return array
     */
    public static function getDescription(): array
    {
        return [
            'USER_TYPE_ID'        => static::USER_TYPE_ID,
            'CLASS_NAME'          => __CLASS__,
            'DESCRIPTION'         => 'Поле с гибкими правами доступа',
            'BASE_TYPE'           => 'string',
            'USE_FIELD_COMPONENT' => true,
            'EDIT_CALLBACK'       => [__CLASS__, 'getPublicEdit'],
            'VIEW_CALLBACK'       => [__CLASS__, 'getPublicView'],
        ];
    }

    /**
     * Тип поля в БД
     * @return string
     */
    public static function getDbColumnType(): string
    {
        return 'text';
    }

    /**
     * Сохраняет настройки поля (Bitrix вызывает только этот метод при сохранении!)
     * @param array $userField
     * @return array
     */
    public static function prepareSettings(array $userField): array
    {
        $req = $_REQUEST['FIELDS']['SETTINGS'] ?? [];

        $settings['ACCESS_SOURCE'] = $req['ACCESS_SOURCE'] ?? 'GROUPS';

        /* --- группы --- */
        if ($settings['ACCESS_SOURCE'] === 'GROUPS') {
            $grp = $req['GROUPS'] ?? [];
            if (!is_array($grp)) $grp = [$grp];
            $grp = array_map('intval', $grp);

            if (!$grp) {            // список пустой → по умолчанию только группа 1
                $grp = [1];
            }
            $settings['GROUPS'] = array_unique($grp);
        }

        /* --- пользователи --- */
        if ($settings['ACCESS_SOURCE'] === 'USERS') {
            $raw = $req['USERS'] ?? '';
            $ids = is_array($raw)
                ? $raw
                : preg_split('/\s*,\s*/', (string)$raw, -1, PREG_SPLIT_NO_EMPTY);
            $settings['USERS'] = array_filter(array_map('intval', $ids));
        }

        return $settings;
    }


    /**
     * Проверяет доступ пользователя к значению поля.
     * @param array $field Массив данных о поле (с ключом SETTINGS и VALUE)
     * @param int $userId ID пользователя
     * @param string $perm 'read' или 'write'
     * @return bool
     */
    public static function checkAccess(array $field, int $userId, string $perm): bool
    {
        global $USER;
        if (is_object($USER) && $USER->IsAdmin()) return true;
        $uGroups = \CUser::GetUserGroup($userId);
        if (in_array(1, $uGroups, true)) return true;

        $set = $field['SETTINGS'] ?? [];
        if (!is_array($set)) return false;
        $src = $set['ACCESS_SOURCE'] ?? 'GROUPS';

        if ($src === 'GROUPS') {
            $allowed = is_array($set['GROUPS'])
                ? $set['GROUPS']
                : preg_split('/\s*,\s*/', (string)$set['GROUPS'], -1, PREG_SPLIT_NO_EMPTY);
            $allowed = array_map('intval', $allowed);
            return (bool)array_intersect($allowed, $uGroups);
        }

        if ($src === 'USERS') {
            $allowed = is_array($set['USERS'])
                ? $set['USERS']
                : preg_split('/\s*,\s*/', (string)$set['USERS'], -1, PREG_SPLIT_NO_EMPTY);
            $allowed = array_map('intval', $allowed);
            return in_array($userId, $allowed, true);
        }

        return false;
    }


    /**
     * HTML-вывод значения в публичной части.
     * Если прав нет — выводим информативное сообщение.
     *
     * @param array      $userField
     * @param array|null $additionalParameters
     * @return string
     */
    public static function getPublicView(array $userField, ?array $additionalParameters = []): string
    {
        $userId = is_object($GLOBALS['USER']) ? (int)$GLOBALS['USER']->GetID() : 0;

        if (!self::checkAccess($userField, $userId, 'read')) {
            return '<span style="color:#c00;">Недостаточно прав для просмотра</span>';
        }

        // Значение поля (строка)
        return htmlspecialcharsbx((string)($userField['VALUE'] ?? ''));
    }


    /**
     * HTML для публичного редактирования поля
     * @param array $userField
     * @param array|null $additionalParameters
     * @return string
     */
    public static function getPublicEdit(array $userField, ?array $additionalParameters = []): string
    {
        $userId = is_object($GLOBALS['USER']) ? (int)$GLOBALS['USER']->GetID() : 0;

        if (!self::checkAccess($userField, $userId, 'write')) {
            return '<span style="color:#c00">Недостаточно прав для редактирования</span>';
        }

        $raw  = (string)($userField['VALUE'] ?? '');
        [$val, $ids] = array_pad(explode('|', $raw, 2), 2, '');

        $val = htmlspecialcharsbx($val);
        $ids = htmlspecialcharsbx($ids);
        $name  = htmlspecialcharsbx($userField['FIELD_NAME']);

        $html  = "<input type='text' name='{$name}_VALUE' value='$val' style='width:70%'>";

        if (($userField['SETTINGS']['ACCESS_SOURCE'] ?? '') === 'FIELD') {
            $html .= "<br><small>ID пользователей (через запятую):</small><br>
                      <input type='text' name='{$name}_IDS' value='$ids' style='width:70%'>";
        }

        $html .= "<input type='hidden' name='{$name}' value='".htmlspecialcharsbx($raw)."' id='{$name}_HIDDEN'>";

        $html .= "<script>
            (function(){
                const fld  = BX('{$name}_VALUE'),
                      ids  = BX('{$name}_IDS'),
                      hid  = BX('{$name}_HIDDEN');
                function pack(){ hid.value = fld.value + '|' + (ids? ids.value : ''); }
                if (fld) fld.oninput = pack;
                if (ids) ids.oninput = pack;
            })();
        </script>";

        return $html;
    }

    /**
     * HTML для формы настроек пользовательского поля (админка)
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
        $html .= '</div>';

        // Чекбоксы групп
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

        // Поле пользователей
        $html .= '<div id="af-users" style="margin-top:6px;'.($source!='USERS'?'display:none':'').'">
            <input type="text" size="40" name="'.$namePrefix.'[USERS]" value="'.htmlspecialcharsbx($selUsers).'"
                   placeholder="ID через запятую">
          </div>';

        $html .= "<script>
    BX.ready(function(){
        let radios = document.getElementsByName('{$namePrefix}[ACCESS_SOURCE]');
        Array.from(radios).forEach(r=>{
            r.onclick = function(){
                BX('af-groups').style.display = (this.value==='GROUPS') ? 'block' : 'none';
                BX('af-users').style.display  = (this.value==='USERS')  ? 'block' : 'none';
            };
        });
    });
    </script>";

        return $html;
    }
}
