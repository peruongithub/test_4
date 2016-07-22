<?php
namespace components;


use trident\DI;
use trident\Request;

class CityModel
{
    use PrepareDBArgumentTrait;

    const SELECT_ALL = '*';

    /**
     * @var $db \PDO
     */
    protected $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @param Request $request
     * @param null|int $id
     * @return array|ModelResponse
     */
    public function getCityData(Request $request, $id = null)
    {
        /**
         * @var $countryModel CountryModel
         */
        $countryModel = DI::get('countryModel');
        $country = $countryModel->extractCountryId($request);
        $valid = $countryModel->validateCountry($request, $country);

        if ($valid instanceof ModelResponse) {
            return $valid;
        }

        if (self::SELECT_ALL === $id) {

            $stmt = $this->db->prepare('SELECT id, name FROM cities WHERE country = :country');


            $stmt->execute(['country' => $country]);

            return $stmt->fetchAll();
        } elseif (is_int($id)) {
            //
        } else {
            $id = $this->extractCityId($request);
        }

        $stmt = $this->db->prepare('SELECT * FROM cities WHERE id = :value AND country = :country');
        $result = $stmt->execute(['value' => $id,'country' => $country]);

        if (!$result) {
            return new ModelResponse('Указанного Вами города нет в базе данных', null, Action::VALIDATE, Status::ERROR);
        }

        return $stmt->fetch();
    }

    public function extractCityId(Request $request, $id = null)
    {
        if (null === $id) {
            $id = $this->prepare($request->param('city', '0'));
        }
        return (int)$id;
    }

    /**
     * @param Request $request
     * @param int|null $id
     * @return bool|ModelResponse
     */
    public function validateCity(Request $request, $id = null)
    {
        if (null === $id) {
            $id = $this->extractCityId($request);
        }else{
            $id = (int)$id;
        }

        $stmt = $this->db->prepare('SELECT `id` FROM cities WHERE id = :value');
        $result = $stmt->execute(['value' => $id]);
        return $result ? true :
            new ModelResponse('Указанного Вами города нет в базе данных', null, Action::VALIDATE, Status::ERROR);
    }

    /**
     * @return ModelResponse
     */
    public final function createSchema()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `cities` (
  `id` INTEGER NOT NULL PRIMARY KEY,
  `country` INTEGER NOT NULL,
  `name` CHAR(45) NOT NULL, 
  CONSTRAINT `FK_city_country` FOREIGN KEY (`country`) REFERENCES country (`id`) 
  ON DELETE RESTRICT ON UPDATE RESTRICT)';

        try {
            $this->db->exec($sql);
        } catch (\PDOException $e) {
            return new ModelResponse($e->getMessage(), null, Action::CREATE, Status::FAILURE);
        }

        return new ModelResponse(
            'Country schema successfully created.',
            null,
            Action::CREATE,
            Status::SUCCESS
        );
    }

    /**
     * @return ModelResponse
     */
    public final function fillData()
    {
        /**
         * @var $country CountryModel
         */
        $countryModel = DI::get('countryModel');
        $countries = $countryModel->getCountryData(Request::initial(), CountryModel::SELECT_ALL);

        $cities = str_replace('  ', '', file_get_contents('./components/cityNames.txt'));
        $cities = explode(' ', $cities);
        $countCities = count($cities);

        foreach ($countries as $country) {
            $sql = [];
            for ($i = 1; 150 >= $i; $i++) {
                $sql[] = '(\'' . $country['id'] . '\',  \'' . $cities[\random_int(1, $countCities) - 1] . '\')';
            }
            $sql = implode(', ', $sql);
            try {
                $sql = 'INSERT OR IGNORE INTO `cities` (`country`, `name`) VALUES ' . $sql;

                $stmt = $this->db->query($sql);

            } catch (\PDOException $e) {
                return new ModelResponse($e->getMessage(), null, Action::INSERT, Status::FAILURE);
            }
            $stmt->closeCursor();
        }

        return new ModelResponse(
            'Table `cities` was successful filled.',
            null,
            Action::INSERT,
            Status::SUCCESS
        );
    }
}