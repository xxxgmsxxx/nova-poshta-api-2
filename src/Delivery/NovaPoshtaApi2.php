<?php

declare(strict_types=1);

namespace LisDev\Delivery;

/**
 * Nova Poshta API Class.
 *
 * @author lis-dev
 *
 * @see https://my.novaposhta.ua/data/API2-200215-1622-28.pdf
 * @see https://github.com/lis-dev
 *
 * @license MIT
 */
class NovaPoshtaApi2
{
    public const API_URI = 'https://api.novaposhta.ua/v2.0';

    /**
     * Key for API NovaPoshta.
     *
     * @var string
     *
     * @see https://my.novaposhta.ua/settings/index#apikeys
     */
    protected string $key;

    /**
     * @var bool Throw exceptions when in response is error
     */
    protected bool $throwErrors = false;

    /**
     * @var string Format of returned data - array, json, xml
     */
    protected string $format = 'array';

    /**
     * @var string Language of response
     */
    protected string $language = 'ru';

    /**
     * @var string Connection type (curl | file_get_contents)
     */
    protected string $connectionType = 'curl';

    /** @var int Connection timeout (in seconds) */
    protected int $timeout = 0;

    /**
     * @var string Areas (loaded from file, because there is no so function in NovaPoshta API 2.0)
     */
    protected string $areas = '';

    /**
     * @var string Set current model for methods save(), update(), delete()
     */
    protected string $model = 'Common';

    /**
     * @var string Set method of current model
     */
    protected string $method = '';

    /**
     * @var array Set params of current method of current model
     */
    protected array $params = [];

    /**
     * Default constructor.
     *
     * @param string $key            NovaPoshta API key
     * @param string $language       Default Language
     * @param bool $throwErrors    Throw request errors as Exceptions
     * @param string $connectionType Connection type (curl | file_get_contents)
     */
    public function __construct(string $key, string $language = 'ru', bool $throwErrors = false, string $connectionType = 'curl')
    {
        $this->throwErrors = $throwErrors;
        $this
            ->setKey($key)
            ->setLanguage($language)
            ->setConnectionType($connectionType)
            ->model('Common');
    }

    /**
     * Setter for key property.
     *
     * @param string $key NovaPoshta API key
     *
     * @return NovaPoshtaApi2
     */
    public function setKey(string $key): static
    {
        $this->key = $key;
        return $this;
    }

    /**
     * Getter for key property.
     *
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Setter for $connectionType property.
     *
     * @param string $connectionType Connection type (curl | file_get_contents)
     *
     * @return $this
     */
    public function setConnectionType(string $connectionType): static
    {
        $this->connectionType = $connectionType;
        return $this;
    }

    /**
     * Getter for $connectionType property.
     *
     * @return string
     */
    public function getConnectionType(): string
    {
        return $this->connectionType;
    }

    /**
     * @param int $timeout
     *
     * @return $this
     */
    public function setTimeout(int $timeout): static
    {
        $this->timeout = (int)$timeout;

        return $this;
    }

    /**
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Setter for language property.
     *
     * @param string $language
     *
     * @return NovaPoshtaApi2
     */
    public function setLanguage(string $language): static
    {
        $this->language = $language;
        return $this;
    }

    /**
     * Getter for language property.
     *
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * Setter for format property.
     *
     * @param string $format Format of returned data by methods (json, xml, array)
     *
     * @return NovaPoshtaApi2
     */
    public function setFormat(string $format): static
    {
        $this->format = $format;
        return $this;
    }

    /**
     * Getter for format property.
     *
     * @return string
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * Prepare data before return it.
     *
     * @param array|string $data
     *
     * @return mixed
     * @throws \Exception
     */
    private function prepare(array|string $data): mixed
    {
        // Returns array
        if ('array' == $this->format) {
            $result = is_array($data)
                ? $data
                : json_decode($data, true);
            // If error exists, throw Exception
            if ($this->throwErrors and array_key_exists('errors', $result) and $result['errors']) {
                throw new \Exception(is_array($result['errors']) ? implode("\n", $result['errors']) : $result['errors']);
            }
            return $result;
        }
        // Returns json or xml document
        return $data;
    }

    /**
     * Converts array to xml.
     *
     * @param array $array
     * @param \SimpleXMLElement|bool $xml
     * @return bool|string
     */
    private function array2xml(array $array, \SimpleXMLElement|bool $xml = false): bool|string
    {
        (false === $xml) and $xml = new \SimpleXMLElement('<root/>');
        foreach ($array as $key => $value) {
            if (is_numeric($key)) {
                $key = 'item';
            }
            if (is_array($value)) {
                $this->array2xml($value, $xml->addChild($key));
            } else {
                $xml->addChild($key, $value);
            }
        }
        return $xml->asXML();
    }

    /**
     * Make request to NovaPoshta API.
     *
     * @param string $model Model name
     * @param string $method Method name
     * @param array|null $params Required params
     * @throws \Exception
     */
    private function request(string $model, string $method, array $params = null)
    {
        // Get required URL
        $url = 'xml' == $this->format
            ? self::API_URI.'/xml/'
            : self::API_URI.'/json/';

        $data = [
            'apiKey' => $this->key,
            'modelName' => $model,
            'calledMethod' => $method,
            'language' => $this->language,
            'methodProperties' => $params,
        ];
        $result = [];
        // Convert data to neccessary format
        $post = 'xml' == $this->format
            ? $this->array2xml($data)
            : json_encode($data);

        if ('curl' == $this->getConnectionType()) {
            $curlConn = curl_init($url);
            if ($curlConn !== false ) {
                curl_setopt($curlConn, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($curlConn, CURLOPT_HTTPHEADER, ['Content-Type: '.('xml' == $this->format ? 'text/xml' : 'application/json')]);
                curl_setopt($curlConn, CURLOPT_HEADER, 0);
                curl_setopt($curlConn, CURLOPT_POST, 1);
                curl_setopt($curlConn, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($curlConn, CURLOPT_POSTFIELDS, $post);

                if ($this->timeout > 0) {
                    curl_setopt($curlConn, CURLOPT_TIMEOUT, $this->timeout);
                    curl_setopt($curlConn, CURLOPT_CONNECTTIMEOUT, $this->timeout);
                }

                $result = curl_exec($curlConn);
                curl_close($curlConn);
            }
        } else {
            $httpOptions = [
                'method' => 'POST',
                'header' => "Content-type: application/x-www-form-urlencoded;\r\n",
                'content' => $post,
            ];

            if ($this->timeout > 0) {
                $httpOptions['timeout'] = $this->timeout;
            }

            $result = file_get_contents($url, false, stream_context_create([
                'http' => $httpOptions,
            ]));
        }

        return $this->prepare($result);
    }

    /**
     * Set current model and empties method and params properties.
     *
     * @param string $model
     *
     * @return NovaPoshtaApi2|string
     */
    public function model(string $model = ''): NovaPoshtaApi2|string|static
    {
        if (!$model) {
            return $this->model;
        }

        $this->model = $model;
        $this->method = '';
        $this->params = [];
        return $this;
    }

    /**
     * Set method of current model property and empties params properties.
     *
     * @param string $method
     *
     * @return NovaPoshtaApi2|string
     */
    public function method(string $method = ''): NovaPoshtaApi2|string|static
    {
        if (!$method) {
            return $this->method;
        }

        $this->method = $method;
        $this->params = [];
        return $this;
    }

    /**
     * Set params of current method/property property.
     *
     * @param array $params
     *
     * @return NovaPoshtaApi2
     */
    public function params(array $params = []): static
    {
        $this->params = $params;
        return $this;
    }

    /**
     * Execute request to NovaPoshta API.
     *
     * @return mixed
     * @throws \Exception
     */
    public function execute(): mixed
    {
        return $this->request($this->model, $this->method, $this->params);
    }

    /**
     * Get tracking information by track number.
     *
     * @param string $track Track number
     *
     * @return mixed
     * @throws \Exception
     */
    public function documentsTracking(string $track): mixed
    {
        $params = ['Documents' => [['DocumentNumber' => $track]]];

        return $this->request('TrackingDocument', 'getStatusDocuments', $params);
    }

    /**
     * Get cities of company NovaPoshta.
     *
     * @param int $page Num of page
     * @param string $findByString Find city by russian or ukrainian word
     * @param string $ref ID of city
     *
     * @return mixed
     * @throws \Exception
     */
    public function getCities(int $page = 0, string $findByString = '', string $ref = ''): mixed
    {
        return $this->request('Address', 'getCities', [
            'Page' => $page,
            'FindByString' => $findByString,
            'Ref' => $ref,
        ]);
    }

    /**
     * Get warehouses by city.
     *
     * @param string $cityRef ID of city
     * @param int $page
     *
     * @return mixed
     * @throws \Exception
     */
    public function getWarehouses(string $cityRef, int $page = 0): mixed
    {
        return $this->request('Address', 'getWarehouses', [
            'CityRef' => $cityRef,
            'Page' => $page,
        ]);
    }

    /**
     * Get warehouse types.
     *
     * @return mixed
     * @throws \Exception
     */
    public function getWarehouseTypes(): mixed
    {
        return $this->request('Address', 'getWarehouseTypes');
    }

    /**
     * Get 5 nearest warehouses by array of strings.
     *
     * @param array $searchStringArray
     *
     * @return mixed
     * @throws \Exception
     */
    public function findNearestWarehouse(array $searchStringArray): mixed
    {
        $searchStringArray = (array) $searchStringArray;
        return $this->request('Address', 'findNearestWarehouse', [
            'SearchStringArray' => $searchStringArray,
        ]);
    }

    /**
     * Get one warehouse by city name and warehouse's description.
     *
     * @param string $cityRef ID of city
     * @param string $description Description like in getted by getWarehouses()
     *
     * @return mixed
     * @throws \Exception
     */
    public function getWarehouse(string $cityRef, string $description = ''): mixed
    {
        $warehouses = $this->getWarehouses($cityRef);
        $error = [];
        $data = [];
        if (is_array($warehouses['data'])) {
            $data = $warehouses['data'][0];
            if (count($warehouses['data']) > 1 && $description) {
                foreach ($warehouses['data'] as $warehouse) {
                    if (false !== mb_stripos($warehouse['Description'], $description)
                    or false !== mb_stripos($warehouse['DescriptionRu'], $description)) {
                        $data = $warehouse;
                        break;
                    }
                }
            }
        }
        // Error
        (!$data) and $error = 'Warehouse was not found';
        // Return data in same format like NovaPoshta API
        return $this->prepare(
            [
                'success' => empty($error),
                'data' => [$data],
                'errors' => (array) $error,
                'warnings' => [],
                'info' => [],
            ]
        );
    }

    /**
     * Get streets list by city and/or search string.
     *
     * @param string $cityRef ID of city
     * @param string $findByString
     * @param int $page
     *
     * @return mixed
     * @throws \Exception
     */
    public function getStreet(string $cityRef, string $findByString = '', int $page = 0): mixed
    {
        return $this->request('Address', 'getStreet', [
            'FindByString' => $findByString,
            'CityRef' => $cityRef,
            'Page' => $page,
        ]);
    }

    /**
     * Find current area in list of areas.
     *
     * @param array  $areas        List of arias, getted from file
     * @param string $findByString Area name
     * @param string $ref          Area Ref ID
     *
     * @return array
     */
    protected function findArea(array $areas, string $findByString = '', string $ref = ''): array
    {
        $data = [];
        if (!$findByString and !$ref) {
            return $data;
        }
        // Try to find current region
        foreach ($areas as $key => $area) {
            // Is current area found by string or by key
            $found = $findByString
                ? ((false !== mb_stripos($area['Description'], $findByString))
                    or (false !== mb_stripos($area['DescriptionRu'], $findByString))
                    or (false !== mb_stripos($area['Area'], $findByString))
                    or (false !== mb_stripos($area['AreaRu'], $findByString)))
                : ($key == $ref);
            if ($found) {
                $area['Ref'] = $key;
                $data[] = $area;
                break;
            }
        }
        return $data;
    }

    /**
     * Get area by name or by ID.
     *
     * @param string $findByString Find area by russian or ukrainian word
     * @param string $ref Get area by ID
     *
     * @return array|string
     * @throws \Exception
     */
    public function getArea(string $findByString = '', string $ref = ''): array|string
    {
        // Load areas list from file
        empty($this->areas) and $this->areas = (include dirname(__FILE__).'/NovaPoshtaApi2Areas.php');
        $data = $this->findArea($this->areas, $findByString, $ref);
        // Error
        $error = [];
        empty($data) and $error = ['Area was not found'];
        // Return data in same format like NovaPoshta API
        return $this->prepare(
            [
                'success' => empty($error),
                'data' => $data,
                'errors' => $error,
                'warnings' => [],
                'info' => [],
            ]
        );
    }

    /**
     * Get areas list by city and/or search string.
     *
     * @param string $ref ID of area
     * @param int $page
     *
     * @return mixed
     * @throws \Exception
     */
    public function getAreas(string $ref = '', int $page = 0): mixed
    {
        return $this->request('Address', 'getAreas', [
            'Ref' => $ref,
            'Page' => $page,
        ]);
    }

    /**
     * Find city from list by name of region.
     *
     * @param array $cities Array from query getCities to NovaPoshta
     * @param string $areaName
     *
     * @return array
     * @throws \Exception
     */
    protected function findCityByRegion(array $cities, string $areaName): array
    {
        $data = [];
        $areaRef = '';
        // Get region id
        $area = $this->getArea($areaName);
        $area['success'] and $areaRef = $area['data'][0]['Ref'];
        if ($areaRef and is_array($cities['data'])) {
            foreach ($cities['data'] as $city) {
                if ($city['Area'] == $areaRef) {
                    $data[] = $city;
                }
            }
        }
        return $data;
    }

    /**
     * Get city by name and region (if it needs).
     *
     * @param string $cityName City's name
     * @param string $areaName Region's name
     * @param string $warehouseDescription Warehouse description to identiry needed city (if it more than 1 in the area)
     *
     * @return array Cities's data Can be returned more than 1 city with the same name
     * @throws \Exception
     */
    public function getCity(string $cityName, string $areaName = '', string $warehouseDescription = ''): array|string
    {
        // Get cities by name
        $cities = $this->getCities(0, $cityName);
        $data = [];
        if (is_array($cities) && is_array($cities['data'])) {
            // If cities more then one, calculate current by area name
            $data = (count($cities['data']) > 1)
                ? $this->findCityByRegion($cities, $areaName)
                : [$cities['data'][0]];
        }
        // Try to identify city by one of warehouses descriptions
        if (count($data) > 1 && $warehouseDescription) {
            foreach ($data as $cityData) {
                $warehouseData = $this->getWarehouse($cityData['Ref'], $warehouseDescription);
                $warehouseDescriptions = [
                    $warehouseData['data'][0]['Description'], 
                    $warehouseData['data'][0]['DescriptionRu']
                ];
                if (in_array($warehouseDescription, $warehouseDescriptions)) {
                    $data = [$cityData];
                    break;
                }
            }
        }
        // Error
        $error = [];
        (!$data) and $error = ['City was not found'];
        // Return data in same format like NovaPoshta API
        return $this->prepare(
            [
                'success' => empty($error),
                'data' => $data,
                'errors' => $error,
                'warnings' => [],
                'info' => [],
            ]
        );
    }

    /**
     * Magic method of calling functions (uses for calling Common Model of NovaPoshta API).
     *
     * @param string $method    Called method of Common Model
     * @param array $arguments Array of params
     */
    public function __call(string $method, array $arguments)
    {
        $common_model_method = [
            'getTypesOfCounterparties',
            'getBackwardDeliveryCargoTypes',
            'getCargoDescriptionList',
            'getCargoTypes',
            'getDocumentStatuses',
            'getOwnershipFormsList',
            'getPalletsList',
            'getPaymentForms',
            'getTimeIntervals',
            'getServiceTypes',
            'getTiresWheelsList',
            'getTraysList',
            'getTypesOfAlternativePayers',
            'getTypesOfPayers',
            'getTypesOfPayersForRedelivery',
        ];
        // Call method of Common model
        if (in_array($method, $common_model_method)) {
            return $this
                ->model('Common')
                ->method($method)
                ->params()
                ->execute();
        }

        return null;
    }

    /**
     * Delete method of current model.
     *
     * @param array $params
     *
     * @return mixed
     * @throws \Exception
     */
    public function delete(array $params): mixed
    {
        return $this->request($this->model, 'delete', $params);
    }

    /**
     * Update method of current model
     * Required params:
     * For ContactPerson model: Ref, CounterpartyRef, FirstName (ukr), MiddleName, LastName, Phone (format 0xxxxxxxxx)
     * For Counterparty model: Ref, CounterpartyProperty (Recipient|Sender), CityRef, CounterpartyType (Organization, PrivatePerson),
     * FirstName (or name of organization), MiddleName, LastName, Phone (0xxxxxxxxx), OwnershipForm (if Organization).
     *
     * @param array $params
     *
     * @return mixed
     * @throws \Exception
     */
    public function update(array $params): mixed
    {
        return $this->request($this->model, 'update', $params);
    }

    /**
     * Save method of current model
     * Required params:
     * For ContactPerson model (only for Organization API key, for PrivatePerson error will be returned):
     *     CounterpartyRef, FirstName (ukr), MiddleName, LastName, Phone (format 0xxxxxxxxx)
     * For Counterparty model:
     *     CounterpartyProperty (Recipient|Sender), CityRef, CounterpartyType (Organization, PrivatePerson),
     *     FirstName (or name of organization), MiddleName, LastName, Phone (0xxxxxxxxx), OwnershipForm (if Organization).
     *
     * @param array $params
     *
     * @return mixed
     * @throws \Exception
     */
    public function save(array $params): mixed
    {
        return $this->request($this->model, 'save', $params);
    }

    /**
     * getCounterparties() function of model Counterparty.
     *
     * @param string $counterpartyProperty Type of Counterparty (Sender|Recipient)
     * @param int|null $page Page number
     * @param string|null $findByString String to search
     * @param string|null $cityRef City ID
     *
     * @return mixed
     * @throws \Exception
     */
    public function getCounterparties(string $counterpartyProperty = 'Recipient', int $page = null, string $findByString = null, string $cityRef = null): mixed
    {
        // Any param can be skipped
        $params = [];
        $params['CounterpartyProperty'] = $counterpartyProperty ?: 'Recipient';
        $page and $params['Page'] = $page;
        $findByString and $params['FindByString'] = $findByString;
        $cityRef and $params['City'] = $cityRef;
        return $this->request('Counterparty', 'getCounterparties', $params);
    }

    /**
     * cloneLoyaltyCounterpartySender() function of model Counterparty
     * The counterparty will be not created immediately, you can wait a long time.
     *
     * @param string $cityRef City ID
     *
     * @return mixed
     * @throws \Exception
     */
    public function cloneLoyaltyCounterpartySender(string $cityRef): mixed
    {
        return $this->request('Counterparty', 'cloneLoyaltyCounterpartySender', ['CityRef' => $cityRef]);
    }

    /**
     * getCounterpartyContactPersons() function of model Counterparty.
     *
     * @param string $ref Counterparty ref
     *
     * @return mixed
     * @throws \Exception
     */
    public function getCounterpartyContactPersons(string $ref): mixed
    {
        return $this->request('Counterparty', 'getCounterpartyContactPersons', ['Ref' => $ref]);
    }

    /**
     * getCounterpartyAddresses() function of model Counterparty.
     *
     * @param string $ref Counterparty ref
     * @param int $page
     *
     * @return mixed
     * @throws \Exception
     */
    public function getCounterpartyAddresses(string $ref, int $page = 0): mixed
    {
        return $this->request('Counterparty', 'getCounterpartyAddresses', ['Ref' => $ref, 'Page' => $page]);
    }

    /**
     * getCounterpartyOptions() function of model Counterparty.
     *
     * @param string $ref Counterparty ref
     *
     * @return mixed
     * @throws \Exception
     */
    public function getCounterpartyOptions(string $ref): mixed
    {
        return $this->request('Counterparty', 'getCounterpartyOptions', ['Ref' => $ref]);
    }

    /**
     * getCounterpartyByEDRPOU() function of model Counterparty.
     *
     * @param string $edrpou EDRPOU code
     * @param string $cityRef City ID
     *
     * @return mixed
     * @throws \Exception
     */
    public function getCounterpartyByEDRPOU(string $edrpou, string $cityRef): mixed
    {
        return $this->request('Counterparty', 'getCounterpartyByEDRPOU', ['EDRPOU' => $edrpou, 'cityRef' => $cityRef]);
    }

    /**
     * Get price of delivery between two cities.
     *
     * @param string $citySender City ID
     * @param string $cityRecipient City ID
     * @param string $serviceType (DoorsDoors|DoorsWarehouse|WarehouseWarehouse|WarehouseDoors)
     * @param float $weight
     * @param float $cost
     *
     * @return mixed
     * @throws \Exception
     */
    public function getDocumentPrice(string $citySender, string $cityRecipient, string $serviceType, float $weight, float $cost): mixed
    {
        return $this->request('InternetDocument', 'getDocumentPrice', [
            'CitySender' => $citySender,
            'CityRecipient' => $cityRecipient,
            'ServiceType' => $serviceType,
            'Weight' => $weight,
            'Cost' => $cost,
        ]);
    }

    /**
     * Get approximately date of delivery between two cities.
     *
     * @param string $citySender City ID
     * @param string $cityRecipient City ID
     * @param string $serviceType (DoorsDoors|DoorsWarehouse|WarehouseWarehouse|WarehouseDoors)
     * @param string $dateTime Date of shipping
     *
     * @return mixed
     * @throws \Exception
     */
    public function getDocumentDeliveryDate(string $citySender, string $cityRecipient, string $serviceType, string $dateTime): mixed
    {
        return $this->request('InternetDocument', 'getDocumentDeliveryDate', [
            'CitySender' => $citySender,
            'CityRecipient' => $cityRecipient,
            'ServiceType' => $serviceType,
            'DateTime' => $dateTime,
        ]);
    }

    /**
     * Get documents list.
     *
     * @param array|null $params List of params
     *                      Not required keys:
     *                      'Ref', 'IntDocNumber', 'InfoRegClientBarcodes', 'DeliveryDateTime', 'RecipientDateTime',
     *                      'CreateTime', 'SenderRef', 'RecipientRef', 'WeightFrom', 'WeightTo',
     *                      'CostFrom', 'CostTo', 'SeatsAmountFrom', 'SeatsAmountTo', 'CostOnSiteFrom',
     *                      'CostOnSiteTo', 'StateIds', 'ScanSheetRef', 'DateTime', 'DateTimeFrom',
     *                      'RecipientDateTime', 'isAfterpayment', 'Page', 'OrderField =>
     *                      [
     *                      IntDocNumber, DateTime, Weight, Cost, SeatsAmount, CostOnSite,
     *                      CreateTime, EstimatedDeliveryDate, StateId, InfoRegClientBarcodes, RecipientDateTime
     *                      ],
     *                      'OrderDirection' => [DESC, ASC], 'ScanSheetRef'
     *
     * @return mixed
     * @throws \Exception
     */
    public function getDocumentList(array $params = null): mixed
    {
        return $this->request('InternetDocument', 'getDocumentList', $params ?: null);
    }

    /**
     * Get document info by ID.
     *
     * @param string $ref Document ID
     *
     * @return mixed
     * @throws \Exception
     */
    public function getDocument(string $ref): mixed
    {
        return $this->request('InternetDocument', 'getDocument', [
            'Ref' => $ref,
        ]);
    }

    /**
     * Generetes report by Document refs.
     *
     * @param array $params Params like getDocumentList with requiered keys
     *                      'Type' => [xls, csv], 'DocumentRefs' => []
     *
     * @return mixed
     * @throws \Exception
     */
    public function generateReport(array $params): mixed
    {
        return $this->request('InternetDocument', 'generateReport', $params);
    }

    /**
     * Check required fields for new InternetDocument and set defaults.
     *
     * @param array &$counterparty Recipient info array
     * @throws \Exception
     */
    protected function checkInternetDocumentRecipient(array &$counterparty): void
    {
        // Check required fields
        if (!$counterparty['FirstName']) {
            throw new \Exception('FirstName is required filed for recipient');
        }
        // MiddleName realy is not required field, but manual says otherwise
        // if ( ! $counterparty['MiddleName'])
        // throw new \Exception('MiddleName is required filed for sender and recipient');
        if (!$counterparty['LastName']) {
            throw new \Exception('LastName is required filed for recipient');
        }
        if (!$counterparty['Phone']) {
            throw new \Exception('Phone is required filed for recipient');
        }
        if (!($counterparty['City'] or $counterparty['CityRef'])) {
            throw new \Exception('City is required filed for recipient');
        }
        if (!($counterparty['Region'] or $counterparty['CityRef'])) {
            throw new \Exception('Region is required filed for recipient');
        }

        // Set defaults
        if (empty($counterparty['CounterpartyType'])) {
            $counterparty['CounterpartyType'] = 'PrivatePerson';
        }
    }

    /**
     * Check required params for new InternetDocument and set defaults.
     *
     * @param array &$params
     * @throws \Exception
     */
    protected function checkInternetDocumentParams(array &$params): void
    {
        if (!$params['Description']) {
            throw new \Exception('Description is required filed for new Internet document');
        }
        if (!$params['Weight']) {
            throw new \Exception('Weight is required filed for new Internet document');
        }
        if (!$params['Cost']) {
            throw new \Exception('Cost is required filed for new Internet document');
        }
        empty($params['DateTime']) and $params['DateTime'] = date('d.m.Y');
        empty($params['ServiceType']) and $params['ServiceType'] = 'WarehouseWarehouse';
        empty($params['PaymentMethod']) and $params['PaymentMethod'] = 'Cash';
        empty($params['PayerType']) and $params['PayerType'] = 'Recipient';
        empty($params['SeatsAmount']) and $params['SeatsAmount'] = '1';
        empty($params['CargoType']) and $params['CargoType'] = 'Cargo';
        if($params['CargoType'] != 'Documents') {
            empty($params['VolumeGeneral']) and $params['VolumeGeneral'] = '0.0004';
            empty($params['VolumeWeight']) and $params['VolumeWeight'] = $params['Weight'];
        }
    }

    /**
     * Create Internet Document by.
     *
     * @param array $sender Sender info.
     *                         Required:
     *                         For existing sender:
     *                         'Description' => String (Full name i.e.), 'City' => String (City name)
     *                         For creating:
     *                         'FirstName' => String, 'MiddleName' => String,
     *                         'LastName' => String, 'Phone' => '000xxxxxxx', 'City' => String (City name), 'Region' => String (Region name),
     *                         'Warehouse' => String (Description from getWarehouses))
     * @param array $recipient Recipient info, same like $sender param
     * @param array $params Additional params of Internet Document
     *                         Required:
     *                         'Description' => String, 'Weight' => Float, 'Cost' => Float
     *                         Recommended:
     *                         'VolumeGeneral' => Float (default = 0.004), 'SeatsAmount' => Int (default = 1),
     *                         'PayerType' => (Sender|Recipient - default), 'PaymentMethod' => (NonCash|Cash - default)
     *                         'ServiceType' => (DoorsDoors|DoorsWarehouse|WarehouseDoors|WarehouseWarehouse - default)
     *                         'CargoType' => String
     * @return mixed
     * @throws \Exception
     */
    public function newInternetDocument(array $sender, array $recipient, array $params): mixed
    {
        // Check for required params and set defaults
        $this->checkInternetDocumentRecipient($recipient);
        $this->checkInternetDocumentParams($params);
        if (empty($sender['CitySender'])) {
            $senderCity = $this->getCity($sender['City'], $sender['Region'], $sender['Warehouse']);
            $sender['CitySender'] = $senderCity['data'][0]['Ref'];
        }
        $sender['CityRef'] = $sender['CitySender'];
        if (empty($sender['SenderAddress']) and $sender['CitySender'] and $sender['Warehouse']) {
            $senderWarehouse = $this->getWarehouse($sender['CitySender'], $sender['Warehouse']);
            $sender['SenderAddress'] = $senderWarehouse['data'][0]['Ref'];
        }
        if (empty($sender['Sender'])) {
            $sender['CounterpartyProperty'] = 'Sender';
            $fullName = trim($sender['LastName'].' '.$sender['FirstName'].' '.$sender['MiddleName']);
            // Set full name to Description if is not set
            if (empty($sender['Description'])) {
                $sender['Description'] = $fullName;
            }
            // Check for existing sender
            $senderCounterpartyExisting = $this->getCounterparties('Sender', 1, $fullName, $sender['CityRef']);
            // Copy user to the selected city if user doesn't exists there
            if (isset($senderCounterpartyExisting['data'][0]['Ref'])) {
                // Counterparty exists
                $sender['Sender'] = $senderCounterpartyExisting['data'][0]['Ref'];
                $contactSender = $this->getCounterpartyContactPersons($sender['Sender']);
                $sender['ContactSender'] = $contactSender['data'][0]['Ref'];
                $sender['SendersPhone'] = isset($sender['Phone']) ? $sender['Phone'] : $contactSender['data'][0]['Phones'];
            }
        }

        // Prepare recipient data
        $recipient['CounterpartyProperty'] = 'Recipient';
        $recipient['RecipientsPhone'] = $recipient['Phone'];
        if (empty($recipient['CityRecipient'])) {
            $recipientCity = $this->getCity($recipient['City'], $recipient['Region'], $recipient['Warehouse']);
            $recipient['CityRecipient'] = $recipientCity['data'][0]['Ref'];
        }
        $recipient['CityRef'] = $recipient['CityRecipient'];
        if (empty($recipient['RecipientAddress'])) {
            $recipientWarehouse = $this->getWarehouse($recipient['CityRecipient'], $recipient['Warehouse']);
            $recipient['RecipientAddress'] = $recipientWarehouse['data'][0]['Ref'];
        }
        if (empty($recipient['Recipient'])) {
            $recipientCounterparty = $this->model('Counterparty')->save($recipient);
            $recipient['Recipient'] = $recipientCounterparty['data'][0]['Ref'];
            $recipient['ContactRecipient'] = $recipientCounterparty['data'][0]['ContactPerson']['data'][0]['Ref'];
        }
        // Full params is merge of arrays $sender, $recipient, $params
        $paramsInternetDocument = array_merge($sender, $recipient, $params);
        // Creating new Internet Document
        return $this->model('InternetDocument')->save($paramsInternetDocument);
    }

    /**
     * Get only link on internet document for printing.
     *
     * @param string $method Called method of NovaPoshta API
     * @param array $documentRefs Array of Documents IDs
     * @param string $type (html_link|pdf_link)
     *
     * @return mixed
     * @throws \Exception
     */
    protected function printGetLink(string $method, array $documentRefs, string $type): mixed
    {
        $data = 'https://my.novaposhta.ua/orders/'.$method.'/orders[]/'.implode(',', $documentRefs)
                .'/type/'.str_replace('_link', '', $type)
                .'/apiKey/'.$this->key;
        // Return data in same format like NovaPoshta API
        return $this->prepare(
            [
                'success' => true,
                'data' => [$data],
                'errors' => [],
                'warnings' => [],
                'info' => [],
            ]
        );
    }

    /**
     * printDocument method of InternetDocument model.
     *
     * @param array|string $documentRefs Array of Documents IDs
     * @param string $type (pdf|html|html_link|pdf_link)
     *
     * @return mixed
     * @throws \Exception
     */
    public function printDocument(array|string $documentRefs, string $type = 'html'): mixed
    {
        $documentRefs = (array) $documentRefs;
        // If needs link
        if ('html_link' == $type or 'pdf_link' == $type) {
            return $this->printGetLink('printDocument', $documentRefs, $type);
        }
        // If needs data
        return $this->request('InternetDocument', 'printDocument', ['DocumentRefs' => $documentRefs, 'Type' => $type]);
    }

    /**
     * printMarkings method of InternetDocument model.
     *
     * @param array|string $documentRefs Array of Documents IDs
     * @param string $type (pdf|new_pdf|new_html|old_html|html_link|pdf_link)
     *
     * @return mixed
     * @throws \Exception
     */
    public function printMarkings(array|string $documentRefs, string $type = 'new_html', $size = '85x85'): mixed
    {
        $documentRefs = (array) $documentRefs;
        $documentSize = $size === '85x85' ? '85x85' : '100x100';
        $method = 'printMarking'.$documentSize;
        // If needs link
        if ('html_link' == $type or 'pdf_link' == $type) {
            return $this->printGetLink($method, $documentRefs, $type);
        }
        // If needs data
        return $this->request('InternetDocument', $method, ['DocumentRefs' => $documentRefs, 'Type' => $type]);
    }
}
