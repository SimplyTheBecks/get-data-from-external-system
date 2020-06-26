<?php

/**
 * Class Utils - класс для вспомогательного функционала
 */
class Utils
{
    /**
     * cURL запрос
     *
     * @param string $url     URL для отправки cURL запроса
     * @param array  $request Тело запроса
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
     * @param array $data Данные
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
                            $attrRow['value'] === ''
                            || is_null($attrRow['value'])
                        ) {
                            $rowRes[$attrName] = "NULL";
                        } else {
                            $rowRes[$attrName] = $attrRow['value'];
                        }
                        break;
                    // текст
                    case 'text':
                        if (
                            $attrRow['value'] === ''
                            || is_null($attrRow['value'])
                        ) {
                            $rowRes[$attrName] = "''";
                        } else {
                            $rowRes[$attrName] = "'" . pg_escape_string($attrRow['value']) . "'";
                        }
                        break;
                    // дата и время
                    case 'timestamp':
                        if (
                            $attrRow['value'] === ''
                            || is_null($attrRow['value'])
                        ) {
                            $rowRes[$attrName] = "NULL";
                        } else {
                            $rowRes[$attrName] = "'" . $attrRow['value'] . "'";
                        }
                        break;
                    // true/false
                    case 'boolean':
                        if ($attrRow['value'] === 'true') {
                            $rowRes[$attrName] = "TRUE";
                        } elseif ($attrRow['value'] === 'false') {
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
