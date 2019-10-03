<?php

require_once(__DIR__ . '/GetProcessingSetData.php');
require_once(__DIR__ . '/../utils.php');

use HrapUtils\Utils as HrapUtils;

/**
 * Class CategoryHierarchy - класс для получения, обработки, сохранения данных по иерархии категории
 */
class CategoryHierarchy extends GetProcessingSetData
{
    // соответствие атрибутов
    protected $attributeMatching = [
        // Code
        'Code' => [
            'attrName' => 'cls_incidence_code', // Код элемента в иерархии категории
            'dataType' => 'text'
        ],
        // Name
        'Name' => [
            'attrName' => 'cls_incidence_name', // Наименование элемента в иерархии категории
            'dataType' => 'text'
        ],
        // Archive
        'Archive' => [
            'attrName' => 'is_archived', // Удаленный
            'dataType' => 'boolean'
        ],
        // Parent
        'Parent[0]' => [
            'attrName' => 'parent', // Идентификатор родителя
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
        $res = HrapUtils::curlRequest(URL_FOR_GET_DATA_FROM_HRAP, $request);

        return $res;
    }

    /**
     * Создание запроса для получения данных
     *
     * @return array
     */
    protected function createRequestForGetData(): array
    {
        // запрос
        $res = [
            'DataModelRequest' => [
                'StartElement' => 'Категория'
            ]
        ];

        return $res;
    }

    /**
     * Сохранение данных
     *
     * @param array $data - массив с данными
     * @return integer
     */
    public function setData(array $data): int
    {
        $res = 0;

        if (empty($data)) return $res;

        // получение данных поля parent (родитель)
        $parentData = $this->getParentData($data);

        foreach ($data as $i => $row) {

            $data[$i]['parent'] = [
                'value' => 0,
                'dataType' => 'integer'
            ];
        }

        // обработка значений перед сохранением
        $data = HrapUtils::dataProcessingBeforeSaving($data);

        // получение массива атрибутов для таблицы, хранящей данные
        $attrArray = $this->getAttrArrayForDataTable();
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

        $request = "INSERT INTO tiod.cls_incidence
                        (" . implode(",\n", $attrArray) . ")
                    VALUES
                        (" . implode("),\n(", $insertValues) . ")
                    ON CONFLICT ON CONSTRAINT cls_incidence_cls_incidence_code_key
                    DO
                        UPDATE
                        SET " . implode(",\n", $updateValues) . "
                    RETURNING id, parent, cls_incidence_code;";

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
     * @param array $data - массив с данными
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
     * @param array $data - массив с данными
     * @param array $parentData - массив с данными поля parent (родитель)
     * @return integer
     */
    private function updateParent(array $data, array $parentData): int
    {
        $res = 0;

        if (empty($data)) return $res;

        foreach ($data as $code => $row) {

            if (
                isset($parentData[$code])
                && isset($data[$parentData[$code]])
            ) {
                $data[$code]['parent'] = $data[$parentData[$code]]['id'];
            }
        }

        $request = "";

        foreach ($data as $row) {

            $request .= "UPDATE tiod.cls_incidence
                        SET parent = " . $row['parent'] . "
                        WHERE id = " . $row['id'] . ";\n";
        }

        $result = pg_query($GLOBALS['db_connection'], $request);

        if ($result) $res = 1;

        return $res;
    }
}
