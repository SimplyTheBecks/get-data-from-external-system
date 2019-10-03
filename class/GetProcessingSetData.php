<?php

require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../utils.php');

use HrapUtils\Utils as HrapUtils;

/**
 * Class GetProcessingSetData - абстрактный класс для получения, обработки, сохранения данных
 */
abstract class GetProcessingSetData
{
    // соответствие атрибутов
    protected $attributeMatching = [];
    // получение данных
    abstract protected function getData();
    // создание запроса для получения данных
    abstract protected function createRequestForGetData();
    // сохранение данных
    abstract protected function setData($data);

    /**
     * Обработка данных
     *
     * @param array $data - массив с данными
     * @return array
     */
    public function dataProcessing(array $data): array
    {
        $res = [];

        if (!empty($data)) {

            $dataProcessing = [];

            if (
                !empty($data['Package'])
                && !empty($data['Package']['Item'])
            ) {
                $dataProcessing = $data['Package']['Item'];
            } else if (
                !empty($data['DataModel'])
                && !empty($data['DataModel']['ObjectType'])
            ) {
                $dataProcessing = $data['DataModel']['ObjectType'];
            } else {
                return $res;
            }
        } else {
            return $res;
        }

        // получение массива атрибутов для таблицы, хранящей данные
        $attrArray = $this->getAttrArrayForDataTable();

        foreach ($dataProcessing as $rowMsg) {

            $rowRes = [];

            foreach ($rowMsg as $itemAttr => $itemAttrRow) {

                switch ($itemAttr) {

                    // основные данные о сообщении
                    case 'date':
                    case 'Code':
                    case 'Name':
                    case 'Archive':

                        // поиск соответствующего атрибута
                        $searchedAttr = $this->searchMatchingAttribute($itemAttr);

                        if (!empty($searchedAttr)) {

                            $rowRes[$searchedAttr['attrName']] = [
                                'value'    => $itemAttrRow,
                                'dataType' => $searchedAttr['dataType']
                            ];
                        }

                        break;

                    // массив типов
                    case 'Type':

                        foreach ($itemAttrRow as $j => $typeRow) {

                            // поиск соответствующего атрибута
                            $searchedAttr = $this->searchMatchingAttribute($itemAttr . '[' . $j . ']');

                            if (!empty($searchedAttr)) {

                                $rowRes[$searchedAttr['attrName']] = [
                                    'value'    => $typeRow['TypeId'],
                                    'dataType' => $searchedAttr['dataType']
                                ];
                            }
                        }

                        break;

                    // массив атрибутов
                    case 'Attribute':

                        foreach ($itemAttrRow as $attrRow) {

                            // поиск соответствующего атрибута
                            $searchedAttr = $this->searchMatchingAttribute($attrRow['AttributeId']);

                            if (!empty($searchedAttr)) {

                                // обработка атрибутов с множественными значениями
                                if (
                                    !empty($rowRes[$searchedAttr['attrName']])
                                    && !empty($searchedAttr['isMultipleValue'])
                                ) {
                                    $rowRes[$searchedAttr['attrName']]['value'] .= ',' . $attrRow['Value'];
                                } else {
                                    $rowRes[$searchedAttr['attrName']] = [
                                        'value'    => $attrRow['Value'],
                                        'dataType' => $searchedAttr['dataType']
                                    ];
                                }
                            }
                        }

                        break;

                    // массив родителей
                    case 'Parent':

                        foreach ($itemAttrRow as $j => $typeRow) {

                            // поиск соответствующего атрибута
                            $searchedAttr = $this->searchMatchingAttribute($itemAttr . '[' . $j . ']');

                            if (!empty($searchedAttr)) {

                                $rowRes[$searchedAttr['attrName']] = [
                                    'value'    => $typeRow['ParentId'],
                                    'dataType' => $searchedAttr['dataType']
                                ];
                            }
                        }

                        break;
                }
            }

            if (!empty($rowRes)) {

                // заполнение массива недостающими атрибутами
                foreach ($attrArray as $attrName => $attrDataType) {

                    if (empty($rowRes[$attrName])) {

                        $rowRes[$attrName] = [
                            'value'    => '',
                            'dataType' => $attrDataType
                        ];
                    }
                }

                $res[] = $rowRes;
            }
        }

        return $res;
    }

    /**
     * Получение массива атрибутов для таблицы, хранящей данные
     *
     * @return array
     */
    protected function getAttrArrayForDataTable(): array
    {
        $res = [];

        foreach ($this->attributeMatching as $attrRow) {

            $res[$attrRow['attrName']] = $attrRow['dataType'];
        }

        return $res;
    }

    /**
     * Поиск соответствующего атрибута для карточки сообщения
     *
     * @param string $hrapAttrName - наименование атрибута в ХРАП
     * @return array
     */
    protected function searchMatchingAttribute(string $hrapAttrName): array
    {
        $res = [];

        if (
            empty($hrapAttrName)
            || empty($this->attributeMatching)
            || !in_array($hrapAttrName, array_keys($this->attributeMatching))
        ) return $res;

        $res = $this->attributeMatching[$hrapAttrName];

        return $res;
    }
}
