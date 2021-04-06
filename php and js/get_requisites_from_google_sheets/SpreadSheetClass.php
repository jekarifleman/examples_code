<?php

/**
 * Обёртка для взаимодействия с Google Sheets API
 * Инструкция по получению ключа доступа к API
 * https://codd-wd.ru/instrukciya-po-polucheniyu-klyucha-servisnogo-akkaunta-google-dlya-raboty-s-sheets-api/
 *
 * Class GSpreadSheet
 */
class GSpreadSheet
{
    protected $scopes = [
        'https://www.googleapis.com/auth/spreadsheets',
        'https://www.googleapis.com/auth/drive',
        'https://www.googleapis.com/auth/drive.file'
    ];
    protected $client;
    protected $service;
    protected $googleAccountKeyFilePath;
    protected $spreadsheetId;

    public function __construct($googleAccountKeyFilePath)
    {
        // Задание обработчика исключений и ошибок
        set_exception_handler("GSpreadsheet::exceptionHandler");
        set_error_handler("GSpreadsheet::errorHandler");

        $this->googleAccountKeyFilePath = __DIR__ . '/' . $googleAccountKeyFilePath;
        putenv( 'GOOGLE_APPLICATION_CREDENTIALS=' . $this->googleAccountKeyFilePath );
        $this->client = new Google_Client();
        $this->client->useApplicationDefaultCredentials();
        $this->client->addScope($this->scopes);
        $this->service = new Google_Service_Sheets($this->client);
    }

    /**
     * Обработчик исключений
     *
     * @param Throwable $exception
     */
    public static function exceptionHandler(Throwable $exception)
    {
        $exceptionClass = get_class($exception);

        if ($exceptionClass === 'Google_Service_Exception') {
            $message = $exception->getErrors()[0]['message'];
        } else {
            $message = $exception->getMessage();
        }

        print $message . "\n";
        print $exception->getFile() . ':' . $exception->getLine() . "\n";
        print $exception->getTraceAsString() . "\n";
        exit(1);
    }

    /**
     * Обработчик ошибок
     *
     * @param $errno
     * @param $errstr
     * @param $errfile
     * @param $errline
     * @return bool
     */
    public static function errorHandler($errno, $errstr, $errfile, $errline)
    {
        switch ($errno)
        {
            case E_USER_ERROR || E_RECOVERABLE_ERROR:
                print "Error: " . $errstr . "\n";
                print $errfile . ':' . $errline . "\n";
                exit(1);
                break;
            case E_USER_WARNING || E_WARNING:
                print "Warning: " . $errstr . "\n";
                print $errfile . ':' . $errline . "\n";
                break;
            case E_USER_NOTICE || E_NOTICE:
                print "Notice: " . $errstr . "\n";
                print $errfile . ':' . $errline . "\n";
                break;
            default:
                print "Unknown: " . $errstr . "\n";
                print $errfile . ':' . $errline . "\n";
                break;
        }
        return true;
    }

    /**
     * Получение объекта Spreadsheet
     *
     * @return Google_Service_Sheets_Spreadsheet
     */
    protected function getSpreadsheet()
    {
        return $this->service->spreadsheets->get($this->spreadsheetId);
    }

    /**
     * Получение свойств Spreadsheet
     *
     * @return Google_Service_Sheets_SpreadsheetProperties
     */
    protected function getSpreadsheetProperties()
    {
        $response = $this->getSpreadsheet();
        return $response->getProperties();
    }

    /**
     * Получение объектов листов Spreadsheet
     *
     * @return Google_Service_Sheets_Sheet
     */
    protected function getSheets()
    {
        $response = $this->getSpreadsheet();
        return $response->getSheets();
    }

    /**
     * Получение объекта листа Spreadsheet по названию
     *
     * @param $sheetTitle
     * @return mixed|null
     */
    protected function getSheet($sheetTitle)
    {
        $sheets = $this->getSheets();

        foreach ($sheets as $sheet)
        {
            $sheetProperties = $this->getSheetProperties($sheet);

            if ($sheetProperties->title === $sheetTitle)
            {
                return $sheet;
            }
        }

        return null;
    }

    /**
     * Получение ID листа Spreadsheet
     *
     * @param $sheetTitle
     * @return mixed
     */
    protected function getSheetId($sheetTitle)
    {
        $sheet = $this->getSheet($sheetTitle);
        return $sheet->properties->sheetId;
    }

    /**
     * Получение свойств листа Spreadsheet
     *
     * @param $sheet
     * @return mixed
     */
    protected function getSheetProperties($sheet)
    {
        return $sheet->getProperties();
    }

    /**
     * Получение свойств сетки листа Spreadsheet
     *
     * @param $sheet
     * @return mixed
     */
    protected function getGridProperties($sheet)
    {
        $sheetProperties = $this->getSheetProperties($sheet);
        return $sheetProperties->getGridProperties();
    }

    /**
     * Получение числового индекса столбца из буквенно-числового
     *
     * @param $index
     * @return float|int
     */
    protected function getColumnNumericIndex($index)
    {
        // Извлечение буквенного индекса столбца
        $columnIndex = preg_replace('/\d/', '',$index);

        // Перевод индекса в верхний регистр и разделение на порядки
        $columnIndex = strtoupper($columnIndex);
        $columnIndex = str_split($columnIndex);

        $length = sizeof($columnIndex);
        $numericIndex = 0;

        for ($i = 0; $i < $length; $i++)
        {
            // Порядковый номер буквы в алфавите
            $order = ord($columnIndex[$i]) - 64;

            // Если порядок индекса является высшим...
            if ($i < ($length - 1))
            {
                // Предотвращение нулевого произведения
                $numericIndex = ($numericIndex === 0) ? 1 : $numericIndex;

                $numericIndex *= $order * 26;
            }
            else
            {
                $numericIndex += $order;
            }
        }

        return ($numericIndex > 0) ? $numericIndex : -1;
    }

    /**
     * Получение числового индекса строки из буквенно-числового
     *
     * @param $index
     * @return float|int
     */
    protected function getRowNumericIndex($index)
    {
        // Извлечение номера строки
        $rowIndex = preg_replace('/[a-zA-Z]/', '', $index);

        return (!empty($rowIndex)) ? (int) $rowIndex : -1;

    }

    /**
     * Добавление листа в Spreadsheet
     *
     * @param $sheetTitle
     * @return Google_Service_Sheets_BatchUpdateSpreadsheetResponse
     */
    public function addSheet($sheetTitle = "")
    {
        // Тело запроса
        $request = [
            "requests" => [
                "addSheet" => [
                    "properties" => [
                        "title" => $sheetTitle,
                        "gridProperties" => [
                            "rowCount" => 500,
                            "columnCount" => 26
                        ],
                        "tabColor" => [
                            "red" => 1.0,
                            "green" => 0.8,
                            "blue" => 0.4
                        ]
                    ]
                ]
            ]
        ];

        // Если лист с таким названием не существует...
        if (!$this->getSheet($sheetTitle))
        {
            $requestBody = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest($request);
            $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $requestBody);
        }
    }

    /**
     * Удаление листа Spreadsheet
     *
     * @param $sheetTitle
     */
    public function deleteSheet($sheetTitle)
    {
        // Тело запроса
        $request = [
            "requests" => [
                "deleteSheet" => [
                    "sheetId" => $this->getSheetId($sheetTitle)
                ]
            ]
        ];

        // Если лист с таким названием существует...
        if ($this->getSheet($sheetTitle) !== null)
        {
            $requestBody = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest($request);
            $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $requestBody);
        }
    }

    /**
     * Задание идентификатора SpreadSheet
     *
     * @param $spreadsheetId
     */
    public function setSpreadsheetId($spreadsheetId)
    {
        $this->spreadsheetId = $spreadsheetId;
    }

    /**
     * Получение названия Spreadsheet
     *
     * @return mixed
     */
    public function getSpreadsheetTitle()
    {
        $spreadsheetProperties = $this->getSpreadsheetProperties();
        return $spreadsheetProperties->title;
    }

    /**
     * Получение количества колонок листа Spreadsheet
     *
     * @param $sheetTitle
     * @return mixed
     */
    public function getSheetColumnCount($sheetTitle)
    {
        $sheet = $this->getSheet($sheetTitle);
        $gridProperties = $this->getGridProperties($sheet);
        return $gridProperties->columnCount;
    }

    /**
     * Получение количества строк листа Spreadsheet
     *
     * @param $sheetTitle
     * @return mixed
     */
    public function getSheetRowCount($sheetTitle)
    {
        $sheet = $this->getSheet($sheetTitle);
        $gridProperties = $this->getGridProperties($sheet);
        return $gridProperties->rowCount;
    }

    /**
     * Получение набора данных листа Spreadsheet
     *
     * @param $sheetTitle
     * @param $startIndex
     * @param $endIndex
     * @return mixed
     */
    public function getSheetRangeData($sheetTitle, $startIndex, $endIndex)
    {
        $range = sprintf("%s!%s:%s", $sheetTitle, $startIndex, $endIndex);
        $options = ['valueRenderOption' => 'FORMATTED_VALUE'];
        $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $range, $options);
        $values = $response->getValues();
        return $values;
    }

    /**
     * Получение всех данных листа Spreadsheet
     *
     * @param $sheetTitle
     * @return mixed
     */
    public function getSheetAllData($sheetTitle)
    {
        $options = ['valueRenderOption' => 'FORMATTED_VALUE'];
        $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $sheetTitle, $options);
        $values = $response->getValues();
        return $values;
    }

    /**
     * Получение данных столбца листа Spreadsheet
     *
     * @param $sheetTitle
     * @param $columnIndex
     * @return mixed
     */
    public function getSheetColumnData($sheetTitle, $columnIndex)
    {
        $columnCount = $this->getSheetColumnCount($sheetTitle);
        $range = sprintf("%s!%s1:%s%s", $sheetTitle, $columnIndex, $columnIndex, $columnCount);
        $options = ['valueRenderOption' => 'FORMATTED_VALUE'];
        $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $range, $options);
        $values = $response->getValues();
        return $values;
    }

    /**
     * Получение данных строки листа Spreadsheet
     *
     * @param $sheetTitle
     * @param $rowIndex
     * @return mixed
     */
    public function getSheetRowData($sheetTitle, $rowIndex)
    {
        $range = sprintf("%s!A%s:ZZZ%s", $sheetTitle, $rowIndex, $rowIndex);
        $options = ['valueRenderOption' => 'FORMATTED_VALUE'];
        $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $range, $options);
        $values = $response->getValues();
        return $values;
    }

    /**
     * Получение данных ячейки листа Spreadsheet
     *
     * @param $sheetTitle
     * @param $startIndex
     * @return mixed
     */
    public function getSheetCellData($sheetTitle, $startIndex)
    {
        $range = sprintf("%s!%s", $sheetTitle, $startIndex);
        $options = ['valueRenderOption' => 'FORMATTED_VALUE'];
        $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $range, $options);
        $values = $response->getValues();
        return $values;
    }

    /**
     * Ассоциирование данных таблицы с заголовочной строкой
     *
     * @param $rawData
     * @return array
     */
    public function getSheetAssociatedData($rawData)
    {
        // Заголовочная строка таблицы
        $header = $rawData[0];

        // Количество столбцов заколовочной строки таблицы
        $headerSize = sizeof($header);

        // Размер массива входных данных
        $rawSize = sizeof($rawData);

        // Результирующий массив
        $result = [];

        for ($i = 1; $i < $rawSize; $i++)
        {
            // Ассоциированная строка данных таблицы
            $row = [];

            for ($headerColumn = 0; $headerColumn < $headerSize; $headerColumn++)
            {
                // Ассоциирование данных с заголовочной строкой таблицы
                $row[$header[$headerColumn]] = (isset($rawData[$i][$headerColumn])) ? $rawData[$i][$headerColumn] : '';
            }

            // Добавление ассоциированной строки данных таблицы в результирующий массив
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Добавление данных в лист Spreadsheet
     *
     * @param $sheetTitle
     * @param $values
     * @param $user_entered
     */
    public function addSheetData($spreadsheetId, $sheetTitle, $values, $user_entered = false)
    {
        $body = new Google_Service_Sheets_ValueRange(['values' => $values]);
        $options = array(
            'valueInputOption' => ($user_entered) ? 'USER_ENTERED' : 'RAW',
            'insertDataOption' => 'INSERT_ROWS'
        );

        echo "<br>$spreadsheetId</br>";

        $this->service->spreadsheets_values->append($spreadsheetId, $sheetTitle, $body, $options);
        //$this->spreadsheets_values->append( $this->spreadsheetId, $sheetTitle, $body, $options);
    }

    /**
     * Обновление данных листа Spreadsheet
     *
     * @param $sheetTitle
     * @param $startIndex
     * @param $values
     * @param $user_entered
     */
    public function updateSheetData($sheetTitle, $startIndex, $values, $user_entered = false)
    {
        $range = sprintf("%s!%s", $sheetTitle, $startIndex);
        $body = new Google_Service_Sheets_ValueRange(['values' => $values]);
        $options = array(
            'valueInputOption' => ($user_entered) ? 'USER_ENTERED' : 'RAW',
        );
        $this->service->spreadsheets_values->update( $this->spreadsheetId, $range, $body, $options);
    }

    /**
     * Очистка набора данных листа Spreadsheet
     *
     * @param $sheetTitle
     * @param $startIndex
     * @param $endIndex
     * @return mixed
     */
    public function clearSheetRangeData($sheetTitle, $startIndex, $endIndex)
    {
        $range = sprintf("%s!%s:%s", $sheetTitle, $startIndex, $endIndex);
        $response = $this->service->spreadsheets_values->clear($this->spreadsheetId, $range, new Google_Service_Sheets_ClearValuesRequest([]));
        return $response;
    }

    /**
     * Очистка всех данных листа Spreadsheet
     *
     * @param $sheetTitle
     * @return mixed
     */
    public function clearSheetAllData($sheetTitle)
    {
        $response = $this->service->spreadsheets_values->clear($this->spreadsheetId, $sheetTitle, new Google_Service_Sheets_ClearValuesRequest([]));
        return $response;
    }

    /**
     * Очистка данных столбца листа Spreadsheet
     *
     * @param $sheetTitle
     * @param $columnIndex
     * @return mixed
     */
    public function clearSheetColumnData($sheetTitle, $columnIndex)
    {
        $columnCount = $this->getSheetColumnsCount($sheetTitle);
        $range = sprintf("%s!%s1:%s%s", $sheetTitle, $columnIndex, $columnIndex, $columnCount);
        $response = $this->service->spreadsheets_values->clear($this->spreadsheetId, $range, new Google_Service_Sheets_ClearValuesRequest([]));
        return $response;
    }

    /**
     * Очистка данных строки листа Spreadsheet
     *
     * @param $sheetTitle
     * @param $rowIndex
     * @return mixed
     */
    public function clearSheetRowData($sheetTitle, $rowIndex)
    {
        $range = sprintf("%s!A%s:ZZZ%s", $sheetTitle, $rowIndex, $rowIndex);
        $response = $this->service->spreadsheets_values->clear($this->spreadsheetId, $range, new Google_Service_Sheets_ClearValuesRequest([]));
        return $response;
    }

    /**
     * Очистка данных ячейки листа Spreadsheet
     *
     * @param $sheetTitle
     * @param $startIndex
     * @return mixed
     */
    public function clearSheetCellData($sheetTitle, $startIndex)
    {
        $range = sprintf("%s!%s", $sheetTitle, $startIndex);
        $response = $this->service->spreadsheets_values->clear($this->spreadsheetId, $range, new Google_Service_Sheets_ClearValuesRequest([]));
        return $response;
    }

    /**
     * Удаление со смещением строк листа Spreadsheet
     *
     * @param $sheetTitle
     * @param $startIndex
     * @param $endIndex
     */
    public function deleteSheetRows($sheetTitle, $startIndex, $endIndex)
    {
        $startRowIndex = $this->getRowNumericIndex($startIndex) - 1; // Индексация с 0
        $endRowIndex = $this->getRowNumericIndex($endIndex); // Конечный индекс не включается в диапазон действия

        // Тело запроса
        $request = [
            "requests" => [
                "deleteRange" => [
                    "range" => [
                        "sheetId" => $this->getSheetId($sheetTitle),
                        "startRowIndex" => $startRowIndex,
                        "endRowIndex" => $endRowIndex,
                    ],
                    "shiftDimension" => "ROWS"
                ],
            ]
        ];

        // Если лист с таким названием существует...
        if ($this->getSheet($sheetTitle) !== null)
        {
            $requestBody = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest($request);
            $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $requestBody);
        }
    }

    /**
     * Удаление со смещением столбцов листа Spreadsheet
     *
     * @param $sheetTitle
     * @param $startIndex
     * @param $endIndex
     */
    public function deleteSheetColumns($sheetTitle, $startIndex, $endIndex)
    {
        $startColumnIndex = $this->getColumnNumericIndex($startIndex) - 1; // Индексация с 0
        $endColumnIndex = $this->getColumnNumericIndex($endIndex); // Конечный индекс не включается в диапазон действия

        // Тело запроса
        $request = [
            "requests" => [
                "deleteRange" => [
                    "range" => [
                        "sheetId" => $this->getSheetId($sheetTitle),
                        "startColumnIndex" => $startColumnIndex,
                        "endColumnIndex" => $endColumnIndex,
                    ],
                    "shiftDimension" => "COLUMNS"
                ],
            ]
        ];

        // Если лист с таким названием существует...
        if ($this->getSheet($sheetTitle) !== null)
        {
            $requestBody = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest($request);
            $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $requestBody);
        }
    }

    /**
     * Объединение ячеек листа Spreadsheet в одну ячейку
     *
     * @param $sheetTitle
     * @param $startIndex
     * @param $endIndex
     * @return Google_Service_Sheets_BatchUpdateSpreadsheetResponse
     */
    public function mergeCellsFully($sheetTitle, $startIndex, $endIndex)
    {
        $startRowIndex = $this->getRowNumericIndex($startIndex) - 1; // Индексация с 0
        $endRowIndex = $this->getRowNumericIndex($endIndex); // Конечный индекс не включается в диапазон действия
        $startColumnIndex = $this->getColumnNumericIndex($startIndex) - 1; // Индексация с 0
        $endColumnIndex = $this->getColumnNumericIndex($endIndex); // Конечный индекс не включается в диапазон действия

        // Тело запроса
        $request = [
            "requests" => [
                "mergeCells" => [
                    "range" => [
                        "sheetId" => $this->getSheetId($sheetTitle),
                        "startRowIndex" => $startRowIndex,
                        "endRowIndex" => $endRowIndex,
                        "startColumnIndex" => $startColumnIndex,
                        "endColumnIndex" => $endColumnIndex
                    ],
                    "mergeType" => "MERGE_ALL"
                ],
            ]
        ];

        // Если лист с таким названием существует...
        if ($this->getSheet($sheetTitle) !== null)
        {
            $requestBody = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest($request);
            $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $requestBody);
        }
    }

    /**
     * Объединение ячеек листа Spreadsheet вертикально
     *
     * @param $sheetTitle
     * @param $startIndex
     * @param $endIndex
     */
    public function mergeCellsVertically($sheetTitle, $startIndex, $endIndex)
    {
        $startRowIndex = $this->getRowNumericIndex($startIndex) - 1; // Индексация с 0
        $endRowIndex = $this->getRowNumericIndex($endIndex); // Конечный индекс не включается в диапазон действия
        $startColumnIndex = $this->getColumnNumericIndex($startIndex) - 1; // Индексация с 0
        $endColumnIndex = $this->getColumnNumericIndex($endIndex); // Конечный индекс не включается в диапазон действия

        // Тело запроса
        $request = [
            "requests" => [
                "mergeCells" => [
                    "range" => [
                        "sheetId" => $this->getSheetId($sheetTitle),
                        "startRowIndex" => $startRowIndex,
                        "endRowIndex" => $endRowIndex,
                        "startColumnIndex" => $startColumnIndex,
                        "endColumnIndex" => $endColumnIndex
                    ],
                    "mergeType" => "MERGE_COLUMNS"
                ],
            ]
        ];

        // Если лист с таким названием существует...
        if ($this->getSheet($sheetTitle) !== null)
        {
            $requestBody = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest($request);
            $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $requestBody);
        }
    }

    /**
     * Объединение ячеек листа Spreadsheet горизонтально
     *
     * @param $sheetTitle
     * @param $startIndex
     * @param $endIndex
     */
    public function mergeCellsHorizontally($sheetTitle, $startIndex, $endIndex)
    {
        $startRowIndex = $this->getRowNumericIndex($startIndex) - 1; // Индексация с 0
        $endRowIndex = $this->getRowNumericIndex($endIndex); // Конечный индекс не включается в диапазон действия
        $startColumnIndex = $this->getColumnNumericIndex($startIndex) - 1; // Индексация с 0
        $endColumnIndex = $this->getColumnNumericIndex($endIndex); // Конечный индекс не включается в диапазон действия

        // Тело запроса
        $request = [
            "requests" => [
                "mergeCells" => [
                    "range" => [
                        "sheetId" => $this->getSheetId($sheetTitle),
                        "startRowIndex" => $startRowIndex,
                        "endRowIndex" => $endRowIndex,
                        "startColumnIndex" => $startColumnIndex,
                        "endColumnIndex" => $endColumnIndex
                    ],
                    "mergeType" => "MERGE_ROWS"
                ],
            ]
        ];

        // Если лист с таким названием существует...
        if ($this->getSheet($sheetTitle) !== null)
        {
            $requestBody = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest($request);
            $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $requestBody);
        }
    }

    /**
     * Разъединение ячеек листа Spreadsheet
     *
     * @param $sheetTitle
     * @param $startIndex
     * @param $endIndex
     */
    public function unmergeCells($sheetTitle, $startIndex, $endIndex)
    {
        $startRowIndex = $this->getRowNumericIndex($startIndex) - 1; // Индексация с 0
        $endRowIndex = $this->getRowNumericIndex($endIndex); // Конечный индекс не включается в диапазон действия
        $startColumnIndex = $this->getColumnNumericIndex($startIndex) - 1; // Индексация с 0
        $endColumnIndex = $this->getColumnNumericIndex($endIndex); // Конечный индекс не включается в диапазон действия

        // Тело запроса
        $request = [
            "requests" => [
                "unmergeCells" => [
                    "range" => [
                        "sheetId" => $this->getSheetId($sheetTitle),
                        "startRowIndex" => $startRowIndex,
                        "endRowIndex" => $endRowIndex,
                        "startColumnIndex" => $startColumnIndex,
                        "endColumnIndex" => $endColumnIndex
                    ]
                ],
            ]
        ];

        // Если лист с таким названием существует...
        if ($this->getSheet($sheetTitle) !== null)
        {
            $requestBody = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest($request);
            $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $requestBody);
        }
    }

    /**
     * Стилизация верхней границы ячеек листа Spreadsheet
     *
     * @param $sheetTitle
     * @param $startIndex
     * @param $endIndex
     */
    public function setSheetCellsTopBorder($sheetTitle, $startIndex, $endIndex)
    {
        $startRowIndex = $this->getRowNumericIndex($startIndex) - 1; // Индексация с 0
        $endRowIndex = $this->getRowNumericIndex($endIndex); // Конечный индекс не включается в диапазон действия
        $startColumnIndex = $this->getColumnNumericIndex($startIndex) - 1; // Индексация с 0
        $endColumnIndex = $this->getColumnNumericIndex($endIndex); // Конечный индекс не включается в диапазон действия

        // Тело запроса
        $request = [
            "requests" => [
                "updateBorders" => [
                    "range" => [
                        "sheetId" => $this->getSheetId($sheetTitle),
                        "startRowIndex" => $startRowIndex,
                        "endRowIndex" => $endRowIndex,
                        "startColumnIndex" => $startColumnIndex,
                        "endColumnIndex" => $endColumnIndex
                    ],
                    "top" => [
                        "style" => "SOLID",
                        "color" => [
                            "red" => 0.0,
                            "green" => 0.0,
                            "blue" => 0.0,
                            "alpha" => 0.0,
                        ]
                    ],
                ],
            ]
        ];

        // Если лист с таким названием существует...
        if ($this->getSheet($sheetTitle) !== null)
        {
            $requestBody = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest($request);
            $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $requestBody);
        }
    }

    /**
     * Стилизация нижней границы ячеек листа Spreadsheet
     *
     * @param $sheetTitle
     * @param $startIndex
     * @param $endIndex
     */
    public function setSheetCellsBottomBorder($sheetTitle, $startIndex, $endIndex)
    {
        $startRowIndex = $this->getRowNumericIndex($startIndex) - 1; // Индексация с 0
        $endRowIndex = $this->getRowNumericIndex($endIndex); // Конечный индекс не включается в диапазон действия
        $startColumnIndex = $this->getColumnNumericIndex($startIndex) - 1; // Индексация с 0
        $endColumnIndex = $this->getColumnNumericIndex($endIndex); // Конечный индекс не включается в диапазон действия

        // Тело запроса
        $request = [
            "requests" => [
                "updateBorders" => [
                    "range" => [
                        "sheetId" => $this->getSheetId($sheetTitle),
                        "startRowIndex" => $startRowIndex,
                        "endRowIndex" => $endRowIndex,
                        "startColumnIndex" => $startColumnIndex,
                        "endColumnIndex" => $endColumnIndex
                    ],
                    "bottom" => [
                        "style" => "SOLID",
                        "color" => [
                            "red" => 0.0,
                            "green" => 0.0,
                            "blue" => 0.0,
                            "alpha" => 0.0,
                        ]
                    ],
                ],
            ]
        ];

        // Если лист с таким названием существует...
        if ($this->getSheet($sheetTitle) !== null)
        {
            $requestBody = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest($request);
            $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $requestBody);
        }
    }

    /**
     * Стилизация левой границы ячеек листа Spreadsheet
     *
     * @param $sheetTitle
     * @param $startIndex
     * @param $endIndex
     */
    public function setSheetCellsLeftBorder($sheetTitle, $startIndex, $endIndex)
    {
        $startRowIndex = $this->getRowNumericIndex($startIndex) - 1; // Индексация с 0
        $endRowIndex = $this->getRowNumericIndex($endIndex); // Конечный индекс не включается в диапазон действия
        $startColumnIndex = $this->getColumnNumericIndex($startIndex) - 1; // Индексация с 0
        $endColumnIndex = $this->getColumnNumericIndex($endIndex); // Конечный индекс не включается в диапазон действия

        // Тело запроса
        $request = [
            "requests" => [
                "updateBorders" => [
                    "range" => [
                        "sheetId" => $this->getSheetId($sheetTitle),
                        "startRowIndex" => $startRowIndex,
                        "endRowIndex" => $endRowIndex,
                        "startColumnIndex" => $startColumnIndex,
                        "endColumnIndex" => $endColumnIndex
                    ],
                    "left" => [
                        "style" => "SOLID",
                        "color" => [
                            "red" => 0.0,
                            "green" => 0.0,
                            "blue" => 0.0,
                            "alpha" => 0.0,
                        ]
                    ],
                ],
            ]
        ];

        // Если лист с таким названием существует...
        if ($this->getSheet($sheetTitle) !== null)
        {
            $requestBody = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest($request);
            $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $requestBody);
        }
    }

    /**
     * Стилизация правой границы ячеек листа Spreadsheet
     *
     * @param $sheetTitle
     * @param $startIndex
     * @param $endIndex
     */
    public function setSheetCellsRightBorder($sheetTitle, $startIndex, $endIndex)
    {
        $startRowIndex = $this->getRowNumericIndex($startIndex) - 1; // Индексация с 0
        $endRowIndex = $this->getRowNumericIndex($endIndex); // Конечный индекс не включается в диапазон действия
        $startColumnIndex = $this->getColumnNumericIndex($startIndex) - 1; // Индексация с 0
        $endColumnIndex = $this->getColumnNumericIndex($endIndex); // Конечный индекс не включается в диапазон действия

        // Тело запроса
        $request = [
            "requests" => [
                "updateBorders" => [
                    "range" => [
                        "sheetId" => $this->getSheetId($sheetTitle),
                        "startRowIndex" => $startRowIndex,
                        "endRowIndex" => $endRowIndex,
                        "startColumnIndex" => $startColumnIndex,
                        "endColumnIndex" => $endColumnIndex
                    ],
                    "right" => [
                        "style" => "SOLID",
                        "color" => [
                            "red" => 0.0,
                            "green" => 0.0,
                            "blue" => 0.0,
                            "alpha" => 0.0,
                        ]
                    ],
                ],
            ]
        ];

        // Если лист с таким названием существует...
        if ($this->getSheet($sheetTitle) !== null)
        {
            $requestBody = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest($request);
            $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $requestBody);
        }
    }

    /**
     * Создание Spreadsheet
     *
     * @param $spreadsheetTitle
     * @param $sheetTitle
     * @return bool
     */
    public function createSpreadsheet($spreadsheetTitle, $sheetTitle = "Sheet1")
    {
        // Тело запроса
        $body = [
            'properties' => [
                'title' => $spreadsheetTitle
            ],
            'sheets' => [
                'properties' => [
                    'title' => $sheetTitle
                ]
            ]
        ];
        $requestBody = new Google_Service_Sheets_Spreadsheet($body);

        // Создание spreadsheet и возврат ID
        $response = $this->service->spreadsheets->create($requestBody);
        return (isset($response->spreadsheetId)) ? $response->spreadsheetId : false;
    }
}
// ?>