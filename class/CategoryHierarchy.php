<?php

require_once(__DIR__ . '/GetProcessingSetData.php');
require_once(__DIR__ . '/../utils.php');

/**
 * Class CategoryHierarchy - класс для получения, обработки, сохранения данных по иерархии категории
 */
class CategoryHierarchy extends GetProcessingSetData
{
    /**
     * @var array Соответствие атрибутов
     */
    protected $attributeMatching = [
        // Код элемента в иерархии категории
        'Code'      => [
            'attrName' => 'cls_incidence_code',
            'dataType' => 'text'
        ],
        // Наименование элемента в иерархии категории
        'Name'      => [
            'attrName' => 'cls_incidence_name',
            'dataType' => 'text'
        ],
        // Удаленный
        'Archive'   => [
            'attrName' => 'is_archived',
            'dataType' => 'boolean'
        ],
        // Идентификатор родителя
        'Parent[0]' => [
            'attrName' => 'parent',
            'dataType' => 'text'
        ]
    ];

    /**
     * Получение данных
     *
     * @return array
     */
    public function getData(): array
    {
        $res = [];

        // создание запроса для получения данных
        $request = $this->createRequestForGetData();

        if (empty($request)) return $res;

        // cURL запрос
        $res = Utils::curlRequest(URL_FOR_GET_DATA_FROM_EXTERNAL_SYS, $request);

        return $res;
    }

    /**
     * Создание запроса для получения данных
     *
     * @return array
     */
    protected function createRequestForGetData(): array
    {
        return [
            'DataModelRequest' => [
                'StartElement' => 'Категория'
            ]
        ];
    }

    /**
     * Сохранение данных
     *
     * @param array $data Данные
     * @return int
     */
    public function setData(array $data): int
    {
        $res = 0;

        if (empty($data)) return $res;

        // получение данных поля parent (родитель)
        $parentData = $this->getParentData($data);

        foreach ($data as $i => $row) {
            $data[$i]['parent'] = [
                'value'    => 0,
                'dataType' => 'integer'
            ];
        }

        // обработка значений перед сохранением
        $data = Utils::dataProcessingBeforeSaving($data);

        // получение атрибутов для таблицы, хранящей данные
        $attrArray = $this->getAttrsForDataTable();
        $attrArray['insert_row'] = 'timestamp';
        ksort($attrArray);
        $attrArray = array_keys($attrArray);

        // данные для добавления
        $insertValues = [];

        foreach ($data as $rowMsg) {
            $rowMsg['insert_row'] = "now() at time zone 'utc'";
            ksort($rowMsg);
            $insertValues[] = implode(",\n", array_values($rowMsg));
        }

        // данные для обновления
        $updateValues = [];

        foreach ($attrArray as $attrName) {
            $updateValues[] = $attrName . " = EXCLUDED." . $attrName;
        }

        $request = "
            INSERT INTO external_sys.cls_incidence
                (" . implode(",\n", $attrArray) . ")
            VALUES
                (" . implode("),\n(", $insertValues) . ")
            ON CONFLICT ON CONSTRAINT cls_incidence_cls_incidence_code_key
            DO
                UPDATE
                SET " . implode(",\n", $updateValues) . "
            RETURNING id, parent, cls_incidence_code;
        ";

        $result = pg_query($GLOBALS['db_connection'], $request);

        if (!$result) return $res;

        $dataForUpdate = [];

        while ($row = pg_fetch_assoc($result)) {
            $dataForUpdate[$row['cls_incidence_code']] = [
                'id'     => $row['id'],
                'parent' => $row['parent']
            ];
        }

        // обновление поля parent (родитель)
        $res = $this->updateParent($dataForUpdate, $parentData);

        return $res;
    }

    /**
     * Получение данных поля parent (родитель)
     *
     * @param array $data Данные
     * @return array
     */
    private function getParentData(array $data): array
    {
        $res = [];

        if (empty($data)) return $res;

        foreach ($data as $iRow) {
            $res[$iRow['cls_incidence_code']['value']] = $iRow['parent']['value'];
        }

        return $res;
    }

    /**
     * Обновление поля parent (родитель)
     *
     * @param array $data       Данные
     * @param array $parentData Данные поля parent (родитель)
     * @return int
     */
    private function updateParent(array $data, array $parentData): int
    {
        $res = 0;

        if (empty($data)) return $res;

        foreach ($data as $code => $row) {
            if (isset($parentData[$code], $data[$parentData[$code]])) {
                $data[$code]['parent'] = $data[$parentData[$code]]['id'];
            }
        }

        $request = "";

        foreach ($data as $row) {
            $request .= "
                UPDATE external_sys.cls_incidence
                SET parent = {$row['parent']}
                WHERE
                    id = {$row['id']};\n
            ";
        }

        $result = pg_query($GLOBALS['db_connection'], $request);

        if ($result) $res = 1;

        return $res;
    }
}
