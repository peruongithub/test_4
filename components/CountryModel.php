<?php
namespace components;


use trident\Request;

class CountryModel
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
     * @param int|null $id
     * @return bool|ModelResponse
     */
    public function validateCountry(Request $request, $id = null)
    {
        if (null === $id) {
            $id = $this->extractCountryId($request);
        }else{
            $id = (int)$id;
        }

        $stmt = $this->db->prepare('SELECT `id` FROM country WHERE id = :value');
        $result = $stmt->execute(['value' => $id]);
        return $result ? true :
            new ModelResponse('Указанной Вами страны нет в базе данных', null, Action::VALIDATE, Status::ERROR);
    }

    /**
     * @param Request $request
     * @param null $id
     * @return int
     */
    public function extractCountryId(Request $request, $id = null)
    {
        if (null === $id) {
            $id = $this->prepare($request->param('country', '0'));
        }
        return (int)$id;
    }
    /**
     * @param Request $request
     * @param null|int $id
     * @return array|ModelResponse
     */
    public function getCountryData(Request $request, $id = null)
    {
        if (self::SELECT_ALL === $id) {
            $stmt = $this->db->prepare('SELECT * FROM country');
            $stmt->execute();

            return $stmt->fetchAll();
        } elseif (is_int($id)) {
            //
        } else {
            $id = $this->extractCountryId($request);
        }

        $stmt = $this->db->prepare('SELECT * FROM country WHERE id = :value');
        $result = $stmt->execute(['value' => $id]);

        if (!$result) {
            return new ModelResponse('Указанной Вами страны нет в базе данных', null, Action::VALIDATE, Status::ERROR);
        }

        return $stmt->fetch();
    }

    /**
     * @return ModelResponse
     */
    public final function createSchema()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `country` (
  `id` INTEGER NOT NULL PRIMARY KEY,
  `code` CHAR(3) NOT NULL UNIQUE,
  `name` CHAR(45) NOT NULL UNIQUE)';

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
        $countries = require('territories.php');

        $sql = [];

        foreach ($countries as $code => $name) {
            $sql[] = "('$code',  '$name')";
        }

        $sql = implode(', ', $sql);

        try {
            $sql = 'INSERT INTO `country` (`code`, `name`) VALUES '.$sql;
            $stmt = $this->db->query($sql);
        } catch (\PDOException $e) {
            return new ModelResponse($e->getMessage(), null, Action::INSERT, Status::FAILURE);
        }

        $stmt->closeCursor();

        return new ModelResponse(
            'Table `country` was successful created and filled.',
            null,
            Action::INSERT,
            Status::SUCCESS
        );
    }
}