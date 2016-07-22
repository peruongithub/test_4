<?php

namespace components;


use trident\DI;
use trident\GeoIP\GeoIPBase;
use trident\Request;

class UserModel extends DataTableModel
{
    use PrepareDBArgumentTrait;

    const LOGIN = 'login';

    const MIN_PASS_LEN = 5;
    const MAX_PASS_LEN = 20;

    const MIN_LOGIN_LEN = 5;
    const MAX_LOGIN_LEN = 20;

    protected $reservedLogin = ['guest', 'admin'];

    protected $dateFormat = 'd F Y';

    /**
     * @var $session Session
     */
    protected $session;

    /**
     * @var $db \PDO
     */
    protected $db;

    /**
     * @var $countryModel CountryModel
     */
    protected $countryModel;

    /**
     * @var $validator Validator
     */
    protected $validator;

    protected $user;

    public function __construct(Session $session, \PDO $db, CountryModel $countryModel)//
    {
        parent::__construct($db);
        $this->session = $session;
        $this->countryModel = $countryModel;
        $this->validator = DI::build('components\\Validator');
    }

    public function getPK()
    {
        return 'id';
    }

    public function getTableName()
    {
        return 'users';
    }

    public function getColumns()
    {
        return [
            ['db' => 'login', 'dt' => 'login'],
            ['db' => 'phone', 'dt' => 'phone'],
            ['db' => 'city', 'dt' => 'city'],
            ['db' => 'invite', 'dt' => 'invite']
        ];
    }

    public function getDataTableDefaults()
    {
        return [
            'columns' => [
                'Логин',
                'Номер телефона',
                'Город',
                'Код инвайта',
            ],
        ];
    }

    public function getDateFormat()
    {
        return $this->dateFormat;
    }

    public function getDefaults()
    {
        return [
            'id' => 0,
            'email' => '',
            'login' => '',
            'name' => '',
            'country_id' => '0',
            'country_code' => GeoIPBase::getClientRegionCode(),
            'country_name' => 'undefined'

        ];
    }

    /**
     * @return void
     */
    public function logout()
    {
        $this->session->delete('uid')->destroy();
    }

    /**
     * @param Request $request
     * @return ModelResponse
     */
    public function login(Request $request)
    {
        $login = $this->prepare($request->param('login', 'guest'));
        $password = $request->param('password', 'defaultPassword');

        $password = $this->cryptPassword($password);

        $stmt = $this->db->prepare(
            '
            SELECT 
              id, 
              login,
              phone,
              invite AS invite_id,
              city AS city_id
            FROM 
              users 
            WHERE 
                login = :login 
              AND 
                password = :password
        '
        );

        $stmt->execute(['login' => $login, 'password' => $password]);

        $result = $stmt->fetch();
        $stmt->closeCursor();

        if (!$result) {
            return new ModelResponse(
                'User with this login not found or wrong password.',
                null,
                Action::GET,
                Status::FAILURE
            );
        }
        $this->user = $result;

        $this->session->start(null);

        $this->session->set('uid', $this->user['id']);

        return new ModelResponse('User logged in.', null, Action::GET, Status::SUCCESS);
    }

    protected function cryptPassword($password)
    {
        return md5($password);
    }

    /**
     * @param Request $request
     * @return ModelResponse
     */
    public function register(Request $request)
    {
        $login = $this->prepare($this->extractLogin($request));

        $valid = $this->validateLogin($request, $login);

        if ($valid instanceof ModelResponse) {
            return $valid;
        }

        // invite
        /**
         * @var $inviteModel InviteModel
         */
        $inviteModel = DI::get('inviteModel');
        $invite = $inviteModel->extractInvite($request);
        $valid = $inviteModel->isValidInvite($request, $invite);

        if ($valid instanceof ModelResponse) {
            return $valid;
        }

        // password
        $password = $request->param('password', 'defaultPassword');
        if (($valid = $this->validatePassword($password)) instanceof ModelResponse) {
            return $valid;
        }
        $password = $this->cryptPassword($password);
        $confirmedPassword = $this->cryptPassword($request->param('confirm_password', 'defaultPassword'));

        if ($password !== $confirmedPassword) {
            return new ModelResponse(
                'Confirm password and New password is not the same, but must be.',
                null,
                Action::VALIDATE,
                Status::ERROR
            );
        }

        // city
        /**
         * @var $inviteModel InviteModel
         */
        $cityModel = DI::get('cityModel');
        $city = $cityModel->extractCityId($request);
        $valid = $cityModel->validateCity($request, $city);

        if ($valid instanceof ModelResponse) {
            return $valid;
        }

        // country
        /**
         * @var $countryModel CountryModel
         */
        $countryModel = DI::get('countryModel');
        $country = $countryModel->extractCountryId($request);
        $valid = $countryModel->validateCountry($request, $country);

        if ($valid instanceof ModelResponse) {
            return $valid;
        }
        // phone
        $phone = $this->extractPhone($request, null);
        if ($phone instanceof ModelResponse) {
            return $phone;
        }
        $valid = $this->validatePhone($request, $phone);
        if ($valid instanceof ModelResponse) {
            return $valid;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO users (login, password, city, phone, invite)
            VALUES (:login, :password, :city, :phone, :invite)"
        );

        $data = [
            'login' => $login,
            'password' => $password,
            'city' => $city,
            'phone' => $phone,
            'invite' => $invite

        ];

        $this->db->beginTransaction();
        try {
            $status1 = $stmt->execute($data);
            $stmt->closeCursor();
            $status2 = $inviteModel->updateInvite($request, $invite);
            if ($status2 instanceof ModelResponse) {
                $this->db->rollBack();
                return $status2;
            }

            $status = $status1 && $status2;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return new ModelResponse($e->getMessage(), null, Action::INSERT, Status::FAILURE);
        }

        if (!$status) {
            $this->db->rollBack();
            return new ModelResponse('Can not create new user account.', null, Action::INSERT, Status::FAILURE);
        }
        $this->db->commit();

        $this->session->start(null);

        $this->session->set('uid', $this->db->lastInsertId('user'));

        return new ModelResponse('New user account was successfully created.', null, Action::INSERT, Status::SUCCESS);
    }

    /**
     * @param Request $request
     * @param null $phone
     * @return bool|ModelResponse
     */
    public function validatePhone(Request $request, $phone = null)
    {
        if (null === $phone) {
            $phone = $this->extractPhone($request, $phone);
        }

        if ($phone instanceof ModelResponse) {
            return $phone;
        }

        if (mb_strlen($phone) < 10) {
            return new ModelResponse(
                'Длина номер телефона не может быть меньше 10 символов',
                null,
                Action::VALIDATE,
                Status::ERROR
            );
        }

        if (mb_strlen($phone) > 15) {
            return new ModelResponse(
                'Длина номер телефона не может быть больше 15 символов',
                null,
                Action::VALIDATE,
                Status::ERROR
            );
        }

        return true;
    }

    protected function extractPhone(Request $request, $phone = null)
    {
        if (null === $phone) {
            $phone = $this->prepare($request->param('phone', '1(10)'));
        }

        if (empty($phone)) {
            return new ModelResponse('Укажите свой номер телефона', null, Action::VALIDATE, Status::ERROR);
        }

        /*'/((?P<country>(\+3)?8)?\s?\(?(?P<operator>0\d{2}){1}\)?\s?(?P<number>'.
            '\d{3}[\-\s]?\d{3}[\-\s]?\d{1}|'.
            '\d{3}[\-\s]?\d{2}[\-\s]?\d{2}|'.
            '\d{3}[\-\s]?\d{1}[\-\s]?\d{3}|'.
            '\d{2}[\-\s]?\d{2}[\-\s]?\d{3}|'.
            '\d{1}[\-\s]?\d{3}[\-\s]?\d{3}){1})/'*/
        $phone = str_replace([' ', '-', '(', ')', '+', '+3'], '', $phone);
        $matches = [];
        if (!preg_match('/((?P<country>(\+3)?8)?(?P<operator>0\d{2}){1}(?P<number>\d{7}){1})/', $phone, $matches)) {
            return new ModelResponse(
                'Не верный номер телефона.',
                $phone,
                Action::VALIDATE,
                Status::ERROR
            );
        }


        return $phone = (!empty($matches['country']) ? $matches['country'] : '') . '(' . $matches['operator'] . ')' . $matches['number'];
    }

    public function validateLogin(Request $request, $login = null)
    {
        if (null === $login) {
            $login = $this->prepare($this->extractLogin($request));
        }

        if (empty($login)) {
            return new ModelResponse('Заполните поле "логин"', null, Action::VALIDATE, Status::ERROR);
        }

        if (mb_strlen($login) < self::MIN_LOGIN_LEN) {
            return new ModelResponse(
                'Длина логина не может быть меньше' . self::MIN_LOGIN_LEN . ' символов',
                null,
                Action::VALIDATE,
                Status::ERROR
            );
        }

        if (mb_strlen($login) > self::MAX_LOGIN_LEN) {
            return new ModelResponse(
                'Длина логина не может быть больше' . self::MAX_LOGIN_LEN . ' символов',
                null,
                Action::VALIDATE,
                Status::ERROR
            );
        }
        if (!$this->validator->alpha_numeric($login)) {
            return new ModelResponse(
                'Не верный логин. Допускаются большие и маленькие буквы латинского алфавита, цифры от 0 до 9',
                null,
                Action::VALIDATE,
                Status::ERROR
            );
        }
        if (!$this->isUnique($login)) {
            return new ModelResponse(
                'Не верный логин. Введенный Вами логин уже используется',
                null,
                Action::VALIDATE,
                Status::ERROR
            );
        }

        return true;
    }

    /**
     * @param string|null $login
     * @return bool
     */
    protected function isUnique($login = null)
    {
        if (in_array($login, $this->reservedLogin)) {
            return false;
        }
        try {
            $stmt = $this->db->prepare('SELECT  `login`  FROM users WHERE  `login`  = :value');

            $stmt->execute(['value' => $login]);
        } catch (\PDOException $e) {
            return false;
        }

        $result = $stmt->fetchColumn();
        $stmt->closeCursor();

        return !$result ? true : false;
    }

    protected function extractLogin(Request $request, $login = null)
    {
        if (null === $login) {
            $login = $this->prepare($request->param('login', 'guest'));
        }
        return strtolower($login);
    }

    /**
     * @param $password
     * @return bool|ModelResponse
     */
    public function validatePassword($password)
    {
        if ('defaultPassword' === $password || empty($password)) {
            return new ModelResponse('Заполните поле "пароль"', null, Action::VALIDATE, Status::ERROR);
        }
        if (mb_strlen($password) < self::MIN_PASS_LEN) {
            return new ModelResponse(
                'Длина пароля не может быть меньше' . self::MIN_PASS_LEN . ' символов',
                null,
                Action::VALIDATE,
                Status::ERROR
            );
        }
        if (mb_strlen($password) > self::MAX_PASS_LEN) {
            return new ModelResponse(
                'Длина пароля не может быть больше' . self::MAX_PASS_LEN . ' символов',
                null,
                Action::VALIDATE,
                Status::ERROR
            );
        }
        if (!$this->validator->alpha_numeric($password)) {
            return new ModelResponse(
                'Не верный пароль. Допускаются большие и маленькие буквы латинского алфавита, цифры от 0 до 9',
                null,
                Action::VALIDATE,
                Status::ERROR
            );
        }

        return true;
    }

    /**
     * @return array
     * @throws \RuntimeException
     */
    public function getUser()
    {
        if (!$this->isGuest()) {
            if (null === $this->user) {
                $uid = (int)$this->prepare($this->session->get('uid', null));

                $stmt = $this->db->prepare('
            SELECT 
              users.id, 
              users.login,
              users.phone,
              users.invite AS invite_id,
              users.city AS city_id
            FROM  users WHERE users.id = :id');

                $stmt->execute(['id' => $uid]);

                $data = $stmt->fetch();
                $stmt->closeCursor();
                if (!$data) {
                    throw new \RuntimeException('Not found user');
                }

                $this->user = $data;
            }

            return $this->user;
        }
        throw new \RuntimeException('Not logged in user');
    }

    /**
     * @return bool
     */
    public function isGuest()
    {
        return null === $this->session->get('uid', null);
    }

    /**
     * @param int|null $uid
     * @return bool
     */
    public function userExist($uid = null)
    {
        if (null === $uid || null === ($uid = $this->session->get('uid', null))) {
            return false;
            //throw new \RuntimeException('User id is not defined');
        }
        $uid = (int)$this->prepare($uid);

        $stmt = $this->db->prepare('SELECT id FROM users WHERE id = :value');

        $stmt->execute(['value' => $uid]);
        $result = $stmt->fetchColumn();
        $stmt->closeCursor();

        return $result ? true : false;
    }

    /**
     * @return ModelResponse
     */
    public final function createSchema()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `users` (
  `id` INTEGER NOT NULL PRIMARY KEY,
  `login` TEXT NOT NULL,
  `password` TEXT NOT NULL,
  `phone` TEXT NOT NULL,
  `city` INTEGER NOT NULL,
  `invite` TEXT NOT NULL,
  CONSTRAINT `FK_users_invite` FOREIGN KEY (`invite`) REFERENCES invites (`invite`) 
  ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `FK_users_city` FOREIGN KEY (`city`) REFERENCES cities (`id`) 
  ON DELETE RESTRICT ON UPDATE RESTRICT
  )';

        try {
            $this->db->exec($sql);
        } catch (\PDOException $e) {
            return new ModelResponse($e->getMessage(), null, Action::CREATE, Status::FAILURE);
        }

        return new ModelResponse(
            'User schema successfully created.',
            null,
            Action::CREATE,
            Status::SUCCESS
        );
    }
}