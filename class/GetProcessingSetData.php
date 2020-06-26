<?php

require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../utils.php');

/**
 * Class GetProcessingSetData - абстрактный класс для получения, обработки, сохранения данных
 */
abstract class GetProcessingSetData
{
    /**
     * @var array Соответствие атрибутов
     */
    protected $attributeMatching = [];

    /**
     * Получение данных
     *
     * @return array
     */
    abstract protected function getData(): array;

    /**
     * Создание запроса для получения данных
     *
     * @return array
     */
    abstract protected function createRequestForGetData(): array;

    /**
     * Сохранение данных
     *
     * @param array $data Данные
     * @return int
     */
    abstract protected function setData(array $data): int;

    /**
     * Обработка данных
     *
     * @param array $data Данные
     * @return array
     */
    public function dataProcessing(array $data): array
    {
        $res = [];

        if (!empty($data)) {
            if (
                !empty($data['Package'])
                && !empty($data['Package']['Item'])
            ) {
                $dataProcessing = $data['Package']['Item'];
            } elseif (
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

        if (empty($dataProcessing)) return $res;

        // получение атрибутов для таблицы, хранящей данные
        $attrArray = $this->getAttrsForDataTable();

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
     * Получение атрибутов для таблицы, хранящей данные
     *
     * @return array
     */
    protected function getAttrsForDataTable(): array
    {
        $res = [];

        foreach ($this->attributeMatching as $attrRow) {
            $res[$attrRow['attrName']] = $attrRow['dataType'];
        }

        return $res;
    }

    /**
     * Поиск соответствующего атрибута
     *
     * @param string $externalSysAttrName Наименование атрибута во внешней системе
     * @return array
     */
    protected function searchMatchingAttribute(string $externalSysAttrName): array
    {
        $res = [];

        if (
            empty($externalSysAttrName)
            || empty($this->attributeMatching)
            || !array_key_exists($externalSysAttrName, $this->attributeMatching)
        ) {
            return $res;
        }

        $res = $this->attributeMatching[$externalSysAttrName];

        return $res;
    }
}
