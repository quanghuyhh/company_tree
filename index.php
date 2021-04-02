<?php

class Model
{
    /**
     * @var {array} $fillable
     */
    protected $fillable = [];

    public function fill($object)
    {
        $_values = get_object_vars($object);
        foreach ($_values as $key => $value) {
            if (!in_array($key, $this->fillable))
                continue;
            $this->{$key} = $value ?? null;
        }

        return $this;
    }
}

class Travel extends Model
{
    public $id;
    public $employeeName;
    public $departure;
    public $destination;
    public $price;
    public $companyId;
    public $createdAt;

    protected $fillable = ['id', 'employeeName', 'departure', 'destination', 'price', 'companyId', 'createdAt'];
}

class Company extends Model
{
    public $id;
    public $name;
    public $cost;
    public $children;
    protected $parentId;
    protected $createdAt;

    protected $fillable = ['id', 'name', 'parentId', 'children', 'cost', 'createdAt'];
}

class APIHelpers
{
    const API_ROOT = "https://5f27781bf5d27e001612e057.mockapi.io/webprovise";
    const COMPANIES_API = "/companies";
    const TRAVELS_API = "/travels";

    static function getCompanies()
    {
        return array_map(function ($item) {
            return (new Company())->fill($item);
        }, self::getJson(self::COMPANIES_API));
    }

    static function getTravels()
    {
        return array_map(function ($item) {
            return (new Travel())->fill($item);
        }, self::getJson(self::TRAVELS_API));
    }

    static function getJson($endpoint): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, self::API_ROOT . $endpoint);
        $result = curl_exec($ch);
        curl_close($ch);

        return json_decode($result);
    }
}

class CommonHelpers
{

    /**
     * @description: Access to protected properties;
     * @param $obj
     * @param $prop
     * @return mixed
     * @throws ReflectionException
     */
    static function getKey($obj, $prop)
    {
        $reflection = new \ReflectionClass($obj);
        $property = $reflection->getProperty($prop);
        $property->setAccessible(true);
        return $property->getValue($obj);
    }

    static function group_by($array, $key)
    {
        $return = array();
        foreach ($array as $val) {
            $return[$val->{$key}][] = $val;
        }
        return $return;
    }


    static function assignTravelsToCompany($companies, $travels)
    {
        $groupTravels = CommonHelpers::group_by($travels, 'companyId');
        array_map(function ($company) use ($groupTravels) {
            $company->cost = array_sum(array_column($groupTravels[$company->id], 'price'));
            return $company;
        }, $companies);
        return $companies;
    }

    static function createTreeAndCalculateCost($items, $root = 0, $key = 'parentId')
    {
        $parents = array();
        foreach ($items as $item) {
            $parents[self::getKey($item, $key)][] = $item;
        }

        return self::calculateChildCost($parents, $parents[$root], $key);
    }

    static function calculateChildCost(&$parents, $children, $key)
    {
        $tree = array();
        if (empty($children)) {
            return $parents;
        }

        foreach ($children as $child) {
            if (isset($parents[$child->id])) {
                $child->children = self::calculateChildCost($parents, $parents[$child->id], $key);
                $child->cost += array_sum(array_column($child->children, 'cost'));
            } else {
                $child->children = [];
            }
            $tree[] = $child;
        }

        return $tree;
    }
}

class TestScript
{
    public function execute()
    {
        $start = microtime(true);

        try {
            $companies = APIHelpers::getCompanies();
            $travels = APIHelpers::getTravels();

            $companies = CommonHelpers::assignTravelsToCompany($companies, $travels);
            $companyTree = CommonHelpers::createTreeAndCalculateCost($companies);

            echo json_encode($companyTree);
        } catch (Exception $exception) {
            echo "<pre>" . $exception->getMessage() . "</pre>";
        }

//        echo 'Total time: ' . (microtime(true) - $start);
    }
}

(new TestScript())->execute();
