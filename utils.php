<?php

namespace HrapUtils;

/**
 * Class Utils - класс для вспомогательного функционала
 */
class Utils
{
    /**
     * cURL запрос
     *
     * @param string $url - url для отправки cURL запроса
     * @param array $request - тело запроса
     * @return array
     */
    public static function curlRequest(string $url, array $request): array
    {
        $res = [];

        if (empty($url) || empty($request)) return $res;

        $post = ['request' => json_encode($request)];

        if ($curl = curl_init($url)) {

            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($curl);

            curl_close($curl);

            $res = json_decode($response, true);
        }

        return $res;
    }

    /**
     * Обработка значений перед сохранением
     *
     * @param array $data - массив с данными
     * @return array
     */
    public static function dataProcessingBeforeSaving(array $data): array
    {
        $res = [];

        if (empty($data)) return $res;

        foreach ($data as $rowMsg) {

            $rowRes = [];

            foreach ($rowMsg as $attrName => $attrRow) {

                $attrRow['value'] = trim($attrRow['value']);

                switch ($attrRow['dataType']) {

                    // число
                    case 'integer':
                    case 'numeric':

                        if (
                            is_null($attrRow['value'])
                            || '' == $attrRow['value']
                        ) {
                            $rowRes[$attrName] = "NULL";
                        } else {
                            $rowRes[$attrName] = $attrRow['value'];
                        }

                        break;

                    // текст
                    case 'text':

                        if (
                            is_null($attrRow['value'])
                            || '' == $attrRow['value']
                        ) {
                            $rowRes[$attrName] = "''";
                        } else {
                            $rowRes[$attrName] = "'" . pg_escape_string($attrRow['value']) . "'";
                        }

                        break;

                    // дата и время
                    case 'timestamp':

                        if (
                            is_null($attrRow['value'])
                            || '' == $attrRow['value']
                        ) {
                            $rowRes[$attrName] = "NULL";
                        } else {
                            $rowRes[$attrName] = "'" . $attrRow['value'] . "'";
                        }

                        break;

                    // true/false
                    case 'boolean':

                        if ('true' == $attrRow['value']) {

                            $rowRes[$attrName] = "TRUE";
                        } else if ('false' == $attrRow['value']) {

                            $rowRes[$attrName] = "FALSE";
                        } else {
                            $rowRes[$attrName] = "NULL";
                        }

                        break;
                }
            }

            if (!empty($rowRes)) {

                $res[] = $rowRes;
            }
        }

        return $res;
    }
}
