<?php // BOY
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
//c 3 по 8 вводим id пользователя и получаем всё о нём и ИИН

/*
$userFields = \CUser::GetByID($userID)->Fetch();
$userFio = $userFields;
//echo "<pre>"; print_r($userFio); echo "</pre>";

//10 - 24 Получаем масив пользователей
// Получаем массив пользователей всех пользователей
$arUsers = [];
$rsUsers = CUser::GetList(($by = 'NAME'), ($order = 'asc'));
while ($arUser = $rsUsers->Fetch()) {
    // Проверяем, является ли пользователь активным
    if ($arUser['ACTIVE'] === 'Y') {
        $arUsers[] = [
            'ID' => $arUser['ID'],
            'ADMIN_NOTES' => $arUser['ADMIN_NOTES'],
            'EMAIL' => $arUser['EMAIL'],
        ];
    }
}
$this->WriteToTrackingService ("Переменная Каз");


//27 -45 Форма письма обходной лист
?>
<!--div style="background-color: #f2f2f2; padding: 10px; border-radius: 5px; margin-bottom: 10px;">
    <p><strong>Уведомление об увольнении сотрудника по заявке обходного листа (№{=Document:ID}).</strong></p>

    <ul style="list-style-type: none; padding-left: 0;">
        <li><b>Отдел ТП:</b> <span style="font-weight: bold; color: #6753FF;">блокировка учетной записи,
                удаление почтового ящика, перемещение ОС необходимо выбрать принимающие лицо в битриксе.</span></li>
        <li><b>Бухгалтерия:</b> <span style="font-weight: bold; color: #6753FF;">проверка задолженности.</span></li>
        <li><b>Руководитель:</b> <span style="font-weight: bold; color: #6753FF;">Проверить задолженность по задачам,
                документам и поставить на контроль</span></li>
    </ul>

    <ul style="list-style-type: none; padding-left: 0;">
        <li><span style="font-weight: bold; color: #FF3300;">Информация об увольняющемся сотруднике.</span></li>
        <li><b>ФИО:</b> <span style="font-weight: bold; color: #666600;">{=Variable:polzov_fio > printable}</span></li>
        <li><b>Компания:</b> <span style="font-weight: bold; color: #666600;">{=Document:NAME}</span></li>
        <li><b>Руководитель:</b> <span style="font-weight: bold; color: #666600;">{=Variable:nach_fio > printable}</span></li>
        <li><b>Дата увольнения:</b> <span style="font-weight: bold; color: #666600;">{=Document:PROPERTY_DATA_UVOLNENIYA}</span></li>
    </ul>
</div-->



<?php
// 49 - 82 Пинг запрос к 1с методу
// URL и данные для авторизации
$url = "https://was.alsi.com:8055/UniversalApplication/hs/123/Ping";
$username = "Exchange";
$password = "Exchange1C";

// Создание контекста для авторизации
$context = stream_context_create([
    "http" => [
        "header" => "Authorization: Basic " . base64_encode("$username:$password"),
    ],
]);

// Выполнение GET-запроса с авторизацией
$response = file_get_contents($url, false, $context);

// Получение заголовков ответа
$headers = $http_response_header;

// Извлечение HTTP-статуса
$status = 0;
$status_code = null;
foreach ($headers as $header) {
    if (strpos($header, 'HTTP/') === 0) {
        $status_code = intval(substr($header, 9, 3));
        break;
    }
}

if ($status_code == 200){
    $status = 200;
}

//$rootActivity->SetVariable("status_ping", $status);



// 90 - 102 +9 часов к полученной дате и времени
$data = "18.03.2024 00:00:00";

// Преобразование строки времени в формат timestamp, который понимает Битрикс
$timestamp = MakeTimeStamp($data, "DD.MM.YYYY HH:MI:SS");

// Добавление 9 часов к временной метке
$timestamp_with_offset = strtotime('+9 hours', $timestamp);

// Преобразование timestamp в формат, поддерживаемый Битриксом
$formatted_date = ConvertTimeStamp($timestamp_with_offset, "FULL");
// Установка значения переменной $pauza
$rootActivity->SetVariable("pauza", $formatted_date);



// 105 - 156 Вытаскиваем список имущества обрабатываем и в зависимости ворзвращаем
$rootActivity = $this->GetRootActivity();
$dateOfDismissal = $rootActivity->GetVariable("data_time"); // Ещё вот так можно таскать перименные
$use = "{{Лицо увольняющиеся}}";
$userID = substr($use, 5);
$userFields = \CUser::GetByID($userID)->Fetch();
$iin = $userFields['UF_INN'];

// URL и данные для авторизации
//$url = "https://was.alsi.com:8055/UniversalApplication/hs/123/OSList/{$iin}/{code}/{barcode}";  //Бой
$url = "https://ws.alsi.com:8055/UniversalApplication_test/hs/123/OSList/{$iin}/{code}/{barcode}"; //Тест
$username = "Exchange";
$password = "Exchange1C";
$res = 0;

// Создание контекста для аутентификации
$context = stream_context_create([
    'http' => [
        'header' => "Authorization: Basic " . base64_encode("$username:$password"),
    ],
]);

// Выполнение GET-запроса
$response = file_get_contents($url, false, $context);

if ($response == '"Не найден список ОС по физ.лицу"') {
    $res = -1;
} else {
// Декодирование JSON-ответа
    $data = json_decode($response, true);

    $res = 1;
// Массив для хранения строк с значениями "Основное средство" и "Инвентарный номер"
    $result = [];

// Обработка каждого элемента массива
    foreach ($data as $item) {
        // Создание строки в формате "Основное средство: [значение OS], Инвентарный номер: [значение Code]"
        $text = "Основное средство: " . $item["OS"] . "\n" . "Инвентарный номер: " . $item["Code"];
        // Добавление строки в массив результатов
        $result[] = $text;
        // Добавление пустой строки
        $result[] = ""; // Пустая строка для разделения
    }

// Преобразование массива в многострочный текст
    $multiLineText = implode("\n", $result);
    $rootActivity->SetVariable("spisok_OS", $multiLineText);
}

$rootActivity->SetVariable("imyshestvo_barma", $res);
*/


// 160 - 209 Логика заполнения БП и запуска но лог не записывается при запуске ОБНОВЛЁННЫЙ НИЖЕ 615
/*if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submit"])) {
    try {
        // Обработка формы и передача данных в бизнес-процесс
        $userCreatId = $_POST["userCreatId"]; // ID автора
        $userId = reset($_POST["USER_NAME"]); // ID выбранного пользователя
        $company = $_POST["COMPANY"]; // Выбранная компания
        $date = $_POST["DATE"]; // Дата увольнения

        // Поля для передачи в бизнес-процесс
        $fields = array(
            'CREATED_BY' => $userCreatId, // ID пользователя, создавшего запись
            'LITSO_UVOLNYAYUSHCHIESYA' => $userId, // ID выбранного пользователя
            'NAME' => $company, // Название компании
            'DATA_UVOLNENIYA' => $date, // Дата увольнения
            'DATE_CREATE' => ConvertTimeStamp(time(), 'FULL') // Текущая дата
        );

        // Добавление элемента в инфоблок
        $iblockElement = new CIBlockElement;
        $elementId = $iblockElement->Add([
            'IBLOCK_ID' => 167, // ID вашего инфоблока
            'NAME' => $company, // Название элемента (может быть другим полем)
            'CREATED_BY' => $userCreatId, // ID пользователя, создавшего элемент
            'PROPERTY_VALUES' => $fields // Значения свойств элемента
        ]);

        if ($elementId) {
            $documentId = array("iblock", "CIBlockDocument", $elementId);


            $runtime = CBPRuntime::GetRuntime();
            try
            {
                $wi = $runtime->CreateWorkflow(1419, $documentId, $fields);
                $wi->Start();
            }
            catch (Exception $e)
            {
                echo $e;
            }
            echo '<script>alert("Элемент успешно добавлен в инфоблок");</script>';
        } else {
            throw new Exception('Ошибка при добавлении элемента в инфоблок');
        }
    } catch (Exception $e) {
        // Обработка исключений
        echo 'Произошла ошибка: ' . $e->getMessage();
    }
}
*/

// 212 - 214 устанавливаем значение в столбце

/*$fields = array('STATUS' => $mes);
CIBlockElement::SetPropertyValuesEx($elementId, false, $fields);
*/


/* //220 - 235 Получает все полэ элемента
$elementId = 467605; // замените на реальный ID элемента
$iblockId = 169; // замените на реальный ID инфоблока

$arElement = CIBlockElement::GetByID($elementId)->GetNext();

if ($arElement) {
    // Получаем все свойства элемента
    $arProperties = [];
    $rsProperties = CIBlockElement::GetProperty($iblockId, $elementId);
    while ($arProperty = $rsProperties->Fetch()) {
        $arProperties[] = $arProperty;
    }
    echo "<pre>";print_r($arProperties);echo "</pre>";
} else {
    echo "Элемент не найден";
}
*/


//242 - 254 запуск БП уже для созданного элемента
/*
// ID типа документа бизнес-процесса
$documentType = "iblock_element";

// ID собственного бизнес-процесса
$workflowTemplateId = 1428;

// Запускаем бизнес-процесс
$documentId = CBPDocument::StartWorkflow(
    $workflowTemplateId,
    array("iblock", "CIBlockDocument", $elementId),
    array(),
    $errors
);
*/


//262 - 264 строки в низ это title

use Bitrix\Main\Page\Asset;


$APPLICATION->SetTitle("Алимжан");


/*
// 270 - 290 Получаем список отделов и подразделений и выводим красиво списком раскрывающимся
$arFilter = array(
    'IBLOCK_ID' => 5, // ID информационного блока
    'GLOBAL_ACTIVE' => 'Y', // Только активные элементы
);

$arSelect = array('ID', 'NAME', 'IBLOCK_SECTION_ID', 'DEPTH_LEVEL');

$rsSections = CIBlockSection::GetTreeList($arFilter, $arSelect);

// Выводим список отделов и подразделений в виде вложенных списков
echo '<select class="form-select" id="departmentSelect" name="DEPARTMENT">';
echo '<option value="">Выберите отдел</option>';

while ($arSection = $rsSections->GetNext()) {
    // Формируем отступы для вложенных подразделов
    $indent = str_repeat('&nbsp;&nbsp;&nbsp;', ($arSection['DEPTH_LEVEL'] - 1) * 2);

    // Выводим название отдела или подразделения с отступами
    echo '<option value="' . $arSection['ID'] . '">' . $indent . $arSection['NAME'] . '</option>';
}
echo '</select>';
*/


// 295 - 318 привязка к пользователю выбор пользователя
/*
?>
    <div class="mb-3">
        <label for="fio" class="form-label fs-5 fw-bold">ФИО увольняющегося сотрудника:</label>
        <?php
        $APPLICATION->IncludeComponent(
            "bitrix:intranet.user.selector",
            "",
            array(
                "INPUT_NAME" => "USER_NAME", // Имя поля ввода
                "INPUT_NAME_STRING" => "USER_NAME_STRING", // Имя скрытого поля для хранения строки (фамилия, имя, отчество)
                "INPUT_NAME_SONETGROUP" => "", // Имя поля для хранения кодов групп сотрудников (не используется)
                "INPUT_VALUE_STRING" => "", // Значение скрытого поля
                "EXTERNAL" => "A", // Внешний вид (A - аватары)
                "MULTIPLE" => "N", // Множественный выбор (N - одиночный выбор)
                "SOCNET_GROUP_ID" => "", // ID группы социальной сети (не используется)
                "SITE_ID" => SITE_ID, // ID сайта
                "NAME_TEMPLATE" => "", // Шаблон имени (по умолчанию используется из настроек Битрикса)
                "SHOW_LOGIN" => "N", // Показывать логин (N - не показывать)
                "POPUP" => "Y", // Использовать всплывающее окно для выбора
            )
        );
        ?>
    </div>
<?php
*/


// 325 - 350 Получаем поля пользователя с моей страницы
/*
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

// Проверяем, авторизован ли пользователь
global $USER;
if (!$USER->IsAuthorized()) {
    echo "Вы не авторизованы!";
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
    exit;
}

// Получаем ID текущего пользователя
$userId = $USER->GetID();

// Загружаем данные пользователя
$rsUser = CUser::GetByID($userId);
$arUser = $rsUser->Fetch();

$adres = $arUser['PERSONAL_STREET'];

// Выводим все поля пользователя
echo "<pre>";
print_r($userId);
echo "</pre>";

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
*/


// 350 - 466 // получаем id пользователя получаем емаил и потом соеденяемся к AD и там отключаем учётку
/*
$userId = 2810;
$rsUser = CUser::GetByID($userId);
$arUser = $rsUser->Fetch();

$adres = $arUser['PERSONAL_STREET'];
$mail = $arUser['EMAIL'];
$ldap_host = "192.168.130.8";
$ldap_user_dn = "Block.empl";
$ldap_password = "UsrBl0k2024+-+*";

// Подключение к LDAP серверу
$ldap_conn = ldap_connect($ldap_host);
if (!$ldap_conn) {
    die("Could not connect to LDAP server.");
}

ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

// Обработка различных значений адреса на основе содержания подстроки
$locations = [
    'Коктем' => 'ALAKOK01',
    'Достык' => 'ALADOS01',
    'Муканова' => 'ALAMUK01',
    'Кокорай' => 'ALAUTM01',
    'Атырау' => 'ALAATY01',
    'Джандосова' => 'ALAZND01'
];

$found = false;
foreach ($locations as $key => $value) {
    if (strpos($adres, $key) !== false) {
        $ldap_dn = $value;
        $found = true;
        break;
    }
}

if (!$found) {
    echo "Адрес не соответствует ни одному из заданных условий.";
    ldap_unbind($ldap_conn);
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
    exit;
}

$ldap_dn = "OU=Users,OU=" . $ldap_dn . ",OU=ALSI,DC=alsi,DC=com";

// Подключение к LDAP серверу
$ldap_conn = ldap_connect("192.168.130.8");
if (!$ldap_conn) {
    die("Не удалось подключиться к LDAP серверу.");
}

// Установка параметров LDAP
ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);

// Аутентификация
if (!ldap_bind($ldap_conn, $ldap_user_dn, $ldap_password)) {
    echo "Ошибка подключения к LDAP. Error: " . ldap_error($ldap_conn);
    ldap_unbind($ldap_conn);
    exit;
}

// Поиск по email
$search_filter = "(mail=$mail)";
$search = ldap_search($ldap_conn, $ldap_dn, $search_filter);

if (!$search) {
    echo "Ошибка поиска. Error: " . ldap_error($ldap_conn);
    ldap_unbind($ldap_conn);
    exit;
}

$entries = ldap_get_entries($ldap_conn, $search);
echo "<pre>"; print_r($entries); echo "</pre>";

if ($entries["count"] > 0) {
    $user_dn = $entries[0]["dn"]; // Получаем DN пользователя
    $newData = ["userAccountControl" => "514"];

    // Попытка обновить данные пользователя
    if (ldap_mod_replace($ldap_conn, $user_dn, $newData)) {
        echo "Пользователь успешно деактивирован.";
    } else {
        echo "Ошибка при деактивации пользователя: " . ldap_error($ldap_conn);
    }
} else {
    echo "Пользователь с таким email не найден. Поиск в других локациях...\n";
    // Перебор всех локаций
    foreach ($locations as $key => $ou_value) {
        $ldap_dn = "OU=Users,OU=" . $ou_value . ",OU=ALSI,DC=alsi,DC=com";
        $search = ldap_search($ldap_conn, $ldap_dn, $search_filter);
        if ($search && ($entries = ldap_get_entries($ldap_conn, $search)) && $entries["count"] > 0) {
            echo "Пользователь найден в локации: $key\n";
            $user_dn = $entries[0]["dn"];
            $newData = ["userAccountControl" => "514"]; // Деактивация

            if (ldap_mod_replace($ldap_conn, $user_dn, $newData)) {
                echo "Пользователь успешно деактивирован.";
                break; // Выход из цикла, если пользователь найден и обработан
            } else {
                echo "Ошибка при деактивации пользователя: " . ldap_error($ldap_conn);
            }
        }
    }

    if ($entries["count"] == 0) {
        echo "Пользователь с таким email не найден во всех проверенных локациях.";
    }
}

ldap_unbind($ldap_conn); // Отключение от LDAP

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
*/


// 469 - 480 Убираем белый фон страницы в битриксе Первый скрипт на верх второй в низ
?>
    <!--script>
        BX.adjust(BX('workarea-content'), {
            style: {
                'display': 'none'
            }
        });
    </script>
    <script>
        BX.append(BX('card-container'), BX('workarea')); //card-container он должен быть id diva
    </script-->
<?php


//489 - 554 Отправляем запрос в ответ получаем файл и открываем
/*
// URL для отправки запроса
$url = 'https://was.alsi.com/Contractors/hs/Kompra/GetPDF';

// Тело запроса
$data = json_encode(["Bin" => "231040040385"]);

// Инициализация cURL
$ch = curl_init($url);

// Настройки cURL
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data))
);

// Выполнение запроса и получение ответа
$response = curl_exec($ch);

// Проверка на ошибки
if (curl_errno($ch)) {
    echo 'Ошибка запроса: ' . curl_error($ch);
    curl_close($ch);
    exit;
}

// Получение HTTP-кода ответа
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Закрытие cURL
curl_close($ch);

// Проверка на успешное выполнение
if ($http_code != 200) {
    echo 'Ошибка: сервер вернул код ответа ' . $http_code;
    exit;
}

// Проверка на пустое тело ответа
if (empty($response)) {
    echo 'Ошибка: пустое тело ответа';
    exit;
}

// Проверка, начинается ли ответ с "%PDF"
if (strpos($response, '%PDF') !== 0) {
    echo 'Ошибка: сервер не вернул PDF-файл';
    exit;
}

// Очистка буфера вывода
while (ob_get_level()) {
    ob_end_clean();
}

// Установка заголовков для отображения файла в браузере
header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($response));
header('Content-Disposition: inline; filename="response_file.pdf"');

// Вывод содержимого файла для отображения в браузере
echo $response;
exit;
*/


//564 - 672 Файл в смарт процес не получилось притянуть пришлось сохронять
// ссылку в тип поля ссылка логика сохроняет файл на сервер и потом сохроняем ссылку
/*
// URL для отправки запроса
$url = 'https://was.alsi.com/Contractors/hs/Kompra/GetPDF';

// Тело запроса
$data = json_encode(["Bin" => "231040040385"]);

// Инициализация cURL
$ch = curl_init($url);

// Настройки cURL
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data))
);

// Выполнение запроса и получение ответа
$response = curl_exec($ch);

// Проверка на ошибки
if (curl_errno($ch)) {
    echo 'Ошибка запроса: ' . curl_error($ch);
    curl_close($ch);
    exit;
}

// Получение HTTP-кода ответа
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Закрытие cURL
curl_close($ch);

// Проверка на успешное выполнение
if ($http_code != 200) {
    echo 'Ошибка: сервер вернул код ответа ' . $http_code;
    exit;
}

// Проверка на пустое тело ответа
if (empty($response)) {
    echo 'Ошибка: пустое тело ответа';
    exit;
}

// Проверка, начинается ли ответ с "%PDF"
if (strpos($response, '%PDF') !== 0) {
    echo 'Ошибка: сервер не вернул PDF-файл';
    exit;
}

// Кодирование содержимого ответа в base64
$responseBase64 = base64_encode($response);

// Декодируем base64 строку
$fileData = base64_decode($responseBase64);

// Проверяем, что декодирование прошло успешно
if ($fileData === false) {
    die('Ошибка декодирования base64');
}

// Указываем директорию для сохранения файла
$uploadDir = '/home/bitrix/www/upload/dokumentKompra/';

// Генерируем уникальное имя файла
$uniqueFileName = uniqid(true) . '.pdf'; // Меняйте расширение файла по необходимости

// Полный путь к файлу
$filePath = $uploadDir . $uniqueFileName;

// Сохраняем файл на сервер
if (file_put_contents($filePath, $fileData) === false) {
    die('Ошибка сохранения файла');
}

// Создаем ссылку на файл
$fileUrl = 'https://bitrix.alsi.com/upload/dokumentKompra/' . $uniqueFileName;

use \Bitrix\Crm\Service\Container;

// Привязываем файл к смарт-процессу
$factoryId = 263; // ID фабрики смарт-процессов, замените на нужный
$itemId = 146;

// Получаем объект фабрики
try {
    $factory = Container::getInstance()->getFactory($itemId);
    $item = $factory->getItem($factoryId);
} catch (Exception $e) {
    echo $e;
}

if ($factory && $item) {
    // Добавляем файл в пользовательское поле
    $item->set('UF_CRM_29_1722659840', $fileUrl); // Поменяйте на нужное UF поле

    // Сохраняем изменения
    $operation = $factory->getUpdateOperation($item);
    $result = $operation->launch();
    if ($result->isSuccess()) {
        echo "Файл успешно сохранен в смарт-процессе.";
    } else {
        echo "Ошибка сохранения файла: " . implode(", ", $result->getErrorMessages());
    }
} else {
    echo "Фабрика или элемент не найдены.";
}
*/


/*
// 681 - 694 Получаем все элементы инфоблока и переборку делаем
$iblockId = 172;
$uidValueToCheck = ''; // Сюда нужно значение по которому будет производится поиск по полю UID

// Получаем все элементы инфоблока
$arFilter = ["IBLOCK_ID" => $iblockId];
$rsElements = CIBlockElement::GetList([], $arFilter, false, false, ["ID", "NAME", "CREATED_BY", "PROPERTY_UID"]);

while ($arElement = $rsElements->GetNext()) {
    // Проверяем наличие и значение поля UID
    if ($arElement['PROPERTY_UID_VALUE'] == $uidValueToCheck) {
        echo "ID элемента: " . $arElement['ID'] . "<br>";
        echo "Создатель элемента (ID): " . $arElement['CREATED_BY'] . "<br>";
    }
}
*/


// 703 - 741 Деолем запрос получам ответ и поля с ответом запихиваем в поля смарт процесса
/*
//$rootActivity = $this->GetRootActivity();
use \Bitrix\Crm\Service\Container;
//$factoryId = '{{ID}}'; // ID фабрики смарт-процессов, замените на нужный
$factoryId = '273';
$itemId = '146';

// Указываем URL с параметром uid
$url = 'https://bitrix.alsi.com/vnd/getIduid.php?uid=123';

// Инициализируем cURL
$ch = curl_init();

// Устанавливаем параметры для cURL
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

// Выполняем запрос и получаем ответ
$response = curl_exec($ch);

// Закрываем cURL
curl_close($ch);

// Преобразуем JSON-ответ в массив
$data = json_decode($response, true);
$factory = Container::getInstance()->getFactory($itemId);
$item = $factory->getItem($factoryId);
// Проверяем и выводим результат
if (isset($data['idElement']) && isset($data['idAuthor'])) {
   $idElement = $data['idElement'];
   $idAuthor = $data['idAuthor'];
   $item->set('UF_CRM_29_1722926577', $idAuthor);
   $item->set('UF_CRM_29_1722926591', $idElement);
   $operation = $factory->getUpdateOperation($item);
   $result = $operation->launch();
   //echo "<pre>"; print_r($result); echo "</pre>";
} else {
   echo 'Не удалось получить данные.';
   //$rootActivity->SetVariable("UF_CRM_29_1722920079", "Ошибка при декодировании JSON.");
}
*/


// 749 - 796 ищем по критериям элемент инфоблока
/*
// Подключаем модуль инфоблоков
if (CModule::IncludeModule("iblock")) {
    // ID инфоблока
    $iblockId = 114;

    // Значения для проверки
    $emailToCheck = 'ТЕСТ';
    $phoneToCheck = '77019889068';

    // Преобразуем email и телефон
    $emailToCheck = mb_strtolower(trim($emailToCheck));
    $phoneToCheck = str_replace(' ', '', trim($phoneToCheck));
    echo "<pre>"; print_r($phoneToCheck); echo "</pre>";
    // Получаем все элементы инфоблока
    $arFilter = ["IBLOCK_ID" => $iblockId];
    $arSelect = ["ID", "NAME", "CREATED_BY", "SEARCHABLE_CONTENT"];
    $rsElements = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);

    while ($obElement = $rsElements->GetNextElement()) {
        $arElement = $obElement->GetFields();

        if ($arElement['SEARCHABLE_CONTENT']) {
            // Преобразуем содержимое в нижний регистр
            $searchableContent = mb_strtolower($arElement['SEARCHABLE_CONTENT']);

            // Проверяем наличие email в тексте
            if (strpos($searchableContent, $emailToCheck) !== false) {
                echo "Найден элемент с ID: " . $arElement['ID'] . "<br>";
            } else {
                // Ищем строку, начинающуюся с +7
                $lines = explode("\n", $searchableContent);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, '+7') === 0) {
                        $cleanedPhone = str_replace(' ', '', $line);
                        if ($cleanedPhone == $phoneToCheck) {
                            echo "Найден элемент с ID: " . $arElement['ID'] . "<br>";
                            break;
                        }
                    }
                }
            }
        }
    }
} else {
    echo "Не удалось подключить модуль инфоблоков.";
}
*/


//807 - 843  по 827 это создание смарт процесса  831 - 842 это добавление определённого товара
//смартпроцесс
/*
$result = cRest::call('user.get');

$params = [
    'item' => [
        'entityTypeId' => 147,
        'fields' => [
            'createdBy' => 2810,
            'updatedBy' => 6397,
            'categoryId' => 44,
            'opened' => 'N',
            'ufCrm28Hod' => 6304,
            'ufCrm28Comment' => "123",
            'ufCrm28Organization' => 'organizac',
            'ufCrm28Employee' => 'работник',
            'ufCrm28Author' => 'author',
            'observers' => ['yf,k.lfntkb']
        ]
    ],
];

$resultItem = CRest::call("crm.item.add", $params['item']);



$productsForBitrix =
    ['productName' => 'Компьютер',
        'quantity' => 1,];

$params = [
    'ownerId' => $resultItem['result']['item']['id'],
    'ownerType' => 'T93',
    'productRows' => ['fields' => ['productName' => 'Компьютер',
        'quantity' => 1,]]
];

$resultProducts = CRest::call("crm.item.productrow.set", $params);
 */


// 853 - 889 Получение ссылки на файл с коментариев задачи
/*
$taskId = 68486; // Замените на ваш ID задачи

// URL вашего вебхука и базовый URL сайта
$webhookUrl = 'https://bitrix.alsi.com/rest/2307/51acr1uy6ag7snk3/';
$baseUrl = 'https://bitrix.alsi.com';

// Получение списка комментариев задачи
$commentsGetUrl = $webhookUrl . 'task.commentitem.getlist.json';
$params = [
    'TASKID' => $taskId,
];

$response = file_get_contents($commentsGetUrl . '?' . http_build_query($params));
$commentsData = json_decode($response, true);

// Массив для хранения ссылок
$fileDownloadUrls = [];

if (!empty($commentsData['result'])) {
    foreach ($commentsData['result'] as $comment) {
        if (!empty($comment['ATTACHED_OBJECTS'])) {
            foreach ($comment['ATTACHED_OBJECTS'] as $attachment) {
                $downloadUrl = $baseUrl . $attachment['DOWNLOAD_URL'];
                $fileDownloadUrls[] = $downloadUrl;
            }
        }
    }
}

// Вывод всех ссылок, разделённых звёздочками
if (!empty($fileDownloadUrls)) {
    echo implode(" ****** ", $fileDownloadUrls);
} else {
    echo "Файлы не найдены.";
}
*/


// 898 - 929 c задачи получаем ссылки на файл
/*
 $taskId = 68486; // ID задачи

if (CModule::IncludeModule("tasks")) {
    $rsTask = CTasks::GetByID($taskId);

    if ($arTask = $rsTask->GetNext()) {
        // Массив для хранения ссылок на файлы
        $downloadLinks = [];

        // Проверка на наличие файлов в UF_TASK_WEBDAV_FILES
        if (!empty($arTask["UF_TASK_WEBDAV_FILES"])) {
            foreach ($arTask["UF_TASK_WEBDAV_FILES"] as $fileId) {
                // Формируем ссылку на скачивание файла
                $downloadUrl = "https://bitrix.alsi.com/bitrix/tools/disk/uf.php?attachedId=" . $fileId . "&action=download&ncc=1";
                // Добавляем ссылку в массив
                $downloadLinks[] = $downloadUrl;
            }
        }

        // Если ссылки найдены, объединяем их с разделителем и выводим
        if (!empty($downloadLinks)) {
            $result = implode(" **** ", $downloadLinks);
            echo $result;
        } else {
            echo "Файлы не найдены.";
        }
    } else {
        echo "Задача не найдена.";
    }
}
 */


// 938 - 960 метод возвращает ссылки на фалы в задаче и можно дёргать из вне
/*
$taskId = 68486; // ID задачи
$downloadLinks = []; // Массив для хранения ссылок на файлы
$zadacha = 'https://bitrix.alsi.com/company/personal/user/6397/tasks/task/view/68486/';

if (CModule::IncludeModule("tasks")) {
    $rsTask = CTasks::GetByID($taskId);

    if ($arTask = $rsTask->GetNext()) {
        // Проверка на наличие файлов в UF_TASK_WEBDAV_FILES
        if (!empty($arTask["UF_TASK_WEBDAV_FILES"])) {
            foreach ($arTask["UF_TASK_WEBDAV_FILES"] as $fileId) {
                // Формируем ссылку на скачивание файла
                $downloadUrl = "https://bitrix.alsi.com/vnd/getfilermm.php?id=" . $fileId;
                // Добавляем ссылку в массив
                $downloadLinks[] = $downloadUrl;
            }
        }

        // Если ссылки найдены, они уже находятся в массиве $downloadLinks
    } else {
        echo "Задача не найдена.";
    }
}
 */





// 935 - 958
/*
// Записываем лог что отправляем 1с
$iblockId = 196;

// Преобразуем JSON в строку
$jsonString = json_encode($data, JSON_UNESCAPED_UNICODE);

// Подготавливаем данные для записи
$elementFields = [
    'IBLOCK_ID' => $iblockId,
    'NAME' => "Анкета кандидата ", // Уникальное имя записи
    'ACTIVE' => 'Y', // Активность элемента
    'PROPERTY_VALUES' => [
        'DANNYE_UKHODYAT_V_1S' => $jsonString, // Код свойства для хранения JSON
    ],
];

// Создаем элемент
$element = new CIBlockElement();
$elementId = $element->Add($elementFields);

if ($elementId) {
    echo "Данные успешно записаны в список. ID элемента: " . $elementId;
}
*/




// 965 - 995 Узнаём стадии сделок
/*
$webhookUrl = "https://crm.prof-ved.ru/rest/1/vhgs280hfug1of12/";

// ID воронки (0 — основная)
$categoryId = 0;

$getStagesUrl = $webhookUrl . "crm.dealcategory.stage.list.json";
$data = [
    "id" => $categoryId
];

$options = [
    "http" => [
        "header" => "Content-Type: application/json",
        "method" => "POST",
        "content" => json_encode($data)
    ]
];

$context = stream_context_create($options);
$stagesResult = file_get_contents($getStagesUrl, false, $context);

if ($stagesResult === false) {
    die("Ошибка: запрос к Bitrix API не удался (crm.dealcategory.stage.list).");
}

$stagesResponse = json_decode($stagesResult, true);

echo "<h3>Список стадий для воронки 0:</h3>";
echo "<pre>";
print_r($stagesResponse);
echo "</pre>";
 */





// 1004 - 1041 Меняем стадию сделки
/*
$webhookUrl = "https://crm.prof-ved.ru/rest/1/vhgs280hfug1of12/";

// ID сделки, которую нужно перевести
$dealId = 129; // Замените на реальный ID сделки

// Стадия "Транспорт найден"
$newStage = "EXECUTING";

$updateDealUrl = $webhookUrl . "crm.deal.update.json";

$data = [
    "id" => $dealId,
    "fields" => [
        "STAGE_ID" => $newStage
    ]
];

$options = [
    "http" => [
        "header"  => "Content-Type: application/json",
        "method"  => "POST",
        "content" => json_encode($data)
    ]
];

$context = stream_context_create($options);
$result  = file_get_contents($updateDealUrl, false, $context);

if ($result === false) {
    die("Ошибка: запрос на обновление сделки не удался.");
}

$response = json_decode($result, true);

echo "<h3>Результат обновления сделки:</h3>";
echo "<pre>";
print_r($response);
echo "</pre>";
 */





// 1048 - 1136 поулчаем руководителя до самого верха даже если отсутсвует
/*
$rootActivity = $this->GetRootActivity();

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

// Замените на свой вебхук
define("WEBHOOK_URL", "https://bitrix.alsi.com/rest/2307/51acr1uy6ag7snk3/");

// ID пользователя, которого проверяем
$userId = 6397;

// Функция для вызова REST API Bitrix24
function callRestMethod($method, $params = []) {
    $url = WEBHOOK_URL . $method . '?' . http_build_query($params);
    $result = file_get_contents($url);
    return json_decode($result, true);
}

// Функция, которая по ID департамента
// находит его руководителя (UF_HEAD) или поднимается выше
function findDepartmentHeadUp($departmentId) {
    while ($departmentId) {
        // 1. Получаем данные о департаменте
        $depInfo = callRestMethod("department.get", ["ID" => $departmentId]);
        if (empty($depInfo['result'][0])) {
            // Если департамент не найден
            break;
        }
        $dep = $depInfo['result'][0];

        // 2. Проверяем, есть ли руководитель в UF_HEAD
        if (!empty($dep['UF_HEAD'])) {
            // Если есть, получаем данные этого пользователя
            $headInfo = callRestMethod("user.get", ["ID" => $dep['UF_HEAD']]);
            if (!empty($headInfo['result'][0])) {
                return $headInfo['result'][0]; // Возвращаем руководителя
            }
        }

        // 3. Если руководителя в этом департаменте нет,
        //    поднимаемся к родительскому департаменту
        if (!empty($dep['PARENT'])) {
            $departmentId = $dep['PARENT'];
        } else {
            // Родителя нет, значит это верхний уровень
            break;
        }
    }
    return null; // Руководитель не найден до самого верха
}

// ------ Основная логика ------

// Получаем информацию о пользователе
$userInfo = callRestMethod("user.get", ["ID" => $userId]);
if (empty($userInfo['result'])) {
    die("Пользователь с ID=$userId не найден.");
}

$user = $userInfo['result'][0];

// Шаг 1. Проверяем непосредственного руководителя (UF_HEAD)
$head = null;
if (!empty($user['UF_HEAD'])) {
    $headInfo = callRestMethod("user.get", ["ID" => $user['UF_HEAD']]);
    if (!empty($headInfo['result'][0])) {
        $head = $headInfo['result'][0];
    }
}

// Шаг 2. Если непосредственный руководитель не найден,
//        ищем через департамент (и его родителей)
if (!$head) {
    if (!empty($user['UF_DEPARTMENT'][0])) {
        $departmentId = $user['UF_DEPARTMENT'][0];
        // Ищем руководителя в департаменте и его родителях
        $head = findDepartmentHeadUp($departmentId);
    }
}

// Вывод результатов
echo "Пользователь: {$user['NAME']} {$user['LAST_NAME']} (ID: {$user['ID']})<br>";
if ($head) {
    $rootActivity->SetVariable("if", "user_" . $head['ID']);
    //echo "Руководитель: {$head['NAME']} {$head['LAST_NAME']} (ID: {$head['ID']})<br>";
} else {
    $rootActivity->SetVariable("erorr", "Руководитель не найден.");
}
 */





// 1145 - 1171 Левую задачу связываем со сделкой
/*
define("WEBHOOK_URL", "https://bitrix.alsi.com/rest/2307/51acr1uy6ag7snk3/");

// ID задачи
$taskId = 78597;
// ID смарт-процесса (элемента CRM)
$crmEntityId = 3372;
// Префикс смарт-процесса
//Документация
//https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=57&LESSON_ID=3805
$crmPrefix = "TBB"; // Используем вычисленный префикс число деситеричное в шестнадцетиричную

// Обновляем задачу, записывая привязку к смарт-процессу
$response = file_get_contents(WEBHOOK_URL . "tasks.task.update?" . http_build_query([
        "taskId" => $taskId,
        "fields" => [
            "UF_CRM_TASK" => ["{$crmPrefix}_{$crmEntityId}"] // Записываем в правильном формате
        ]
    ]));

$result = json_decode($response, true);

if (!empty($result['result'])) {
    //echo  !";
    $rootActivity->SetVariable("SPETSIFIKAYATSIYA", "Задача: " . $taskId . " успешно привязана к смарт-процессу " . $crmEntityId);
} else {
    $rootActivity->SetVariable("SPETSIFIKAYATSIYA", "Ошибка при обновлении: " . $result);
}
 */




// 1179 - 1206 смарт процесс обновляем стадию на провал
/*
define("WEBHOOK_URL", "https://bitrix.alsi.com/rest/2307/51acr1uy6ag7snk3/");

// ID смарт-процесса (элемента CRM)
$crmEntityId = 845;
$crmEntityTypeId = 187; // ID типа смарт-процесса

// ID стадии отказа (провала)
$failureStageId = "DT187_31:FAIL";

// Запрос на обновление стадии
$response = file_get_contents(WEBHOOK_URL . "crm.item.update?" . http_build_query([
        "entityTypeId" => $crmEntityTypeId,
        "id"           => $crmEntityId,
        "fields"       => [
            "stageId" => $failureStageId
        ]
    ]));

$result = json_decode($response, true);

echo "<pre>";
if (!empty($result['result'])) {
    echo "✅ Карточка $crmEntityId успешно переведена в стадию отказа ($failureStageId)!";
} else {
    echo "❌ Ошибка при обновлении:\n";
    print_r($result);
}
echo "</pre>";
 */






/*
//1217 Я с вами в команде! убирает рассылку
require_once __DIR__ . '/lib/disable_im_events.php';
*/








/*
// 1229 - 1239 Этот код полностью сбрасывает интеграцию с OnlyOffice Cloud и настройки онлайн-редактирования документов в Bitrix24
use Bitrix\Disk\Configuration;
use Bitrix\Disk\Document\BitrixHandler;
use Bitrix\Disk\Document\OnlyOffice\Models\DocumentSessionTable;
use Bitrix\Disk\UserConfiguration;

\Bitrix\Main\Loader::requireModule('disk');

(new \Bitrix\Disk\Document\OnlyOffice\Configuration())->resetCloudRegistration();
UserConfiguration::resetDocumentServiceForAllUsers();
Configuration::setDefaultViewerService(BitrixHandler::getCode());
DocumentSessionTable::clearTable();
*/





/*
class CRest
{
    // Вебхук для доступа к API Bitrix
    private static $webhookUrl = "хук";

    // Метод для выполнения одиночного API-запроса
    public static function call($method, $params = [])
    {
        $url = self::$webhookUrl . $method . ".json";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    // Метод для выполнения пакетного API-запроса
    public static function callBatch($batch)
    {
        $params = [
            'cmd' => $batch,
        ];

        $result = self::call('batch', $params);

        return $result;
    }
}
*/





/**
 * Скрытие пунктов в верхнем (горизонтальном) меню Bitrix24.
 * © Kassenov Alimzhan, 11.06.2025
 */


//echo "<pre>"; print_r($userId); echo "</pre>";
//$rootActivity = $this->GetRootActivity();
//$rootActivity->SetVariable("SPETSIFIKAYATSIYA", $result);


$APPLICATION->IncludeComponent(
    'bitrix:news.list',
    'hierarchy_grid',
    [
        'IBLOCK_ID'   => 19,      // ваш инфоблок
        'NEWS_COUNT'  => 100,
        'SORT_BY1'    => 'SORT',
        'SORT_ORDER1' => 'ASC',
        'CACHE_TYPE'  => 'A',
        'CACHE_TIME'  => 3600,
    ]
);


//echo "<pre>"; print_r($userId); echo "</pre>";
//$rootActivity = $this->GetRootActivity();
//$rootActivity->SetVariable("SPETSIFIKAYATSIYA", $result);
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
?>