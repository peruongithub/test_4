<?php
namespace components;


use trident\DI;
use trident\Request;

class InviteModel extends DataTableModel
{
    use PrepareDBArgumentTrait;

    const SELECT_ALL = '*';

    protected $dateFormat = 'd F Y H:i';

    /**
     * @var $validator Validator
     */
    protected $validator;

    public function __construct(\PDO $db)
    {
        parent::__construct($db);

        $this->validator = DI::build('components\\Validator');
    }

    public function getPK()
    {
        return 'invite';
    }

    public function getTableName()
    {
        return 'invites';
    }

    public function getColumns()
    {
        return [
            ['db' => 'invite', 'dt' => 'invite'],
            ['db' => 'status', 'dt' => 'status'],
            [
                'db' => 'date',
                'dt' => 'date',
                'formatter' => function ($d, $row) {
                    return (!empty($d) && 'null' != $d) ? (new \DateTime('', LocaleDateTime::getTimeZone()))->setTimestamp($d)->format(
                        $this->dateFormat
                    ) : '-';
                },
            ],
        ];
    }

    public function getDefaults()
    {
        return [
            'columns' => [
                'Код инвайта',
                'Дата регистрации',
                'Статус',
            ],
        ];
    }

    public function updateInvite(Request $request, $invite = null)
    {
        if (null === $invite) {
            $invite = $this->extractInvite($request, $invite);
            $valid = $this->isValidInvite($request, $invite);

            if ($valid instanceof ModelResponse) {
                return $valid;
            }
        }

        $stmt = $this->db->prepare('UPDATE invites SET `date` = :date ,`status` = \'1\' WHERE `invite` = :invite');
        $result = $stmt->execute(['date' => time(),'invite' => $invite]);
        $stmt->closeCursor();
        return $result;
    }

    /**
     * @param Request $request
     * @param null $invite
     * @return true|ModelResponse
     */
    public function isValidInvite(Request $request, $invite = null)
    {
        if (null === $invite) {
            $invite = $this->extractInvite($request, $invite);
        }
        if (strlen($invite) != 6 || !$this->validator->numeric($invite)) {
            return new ModelResponse('Код инвайта должен иметь 6 цифр.', null, Action::VALIDATE, Status::ERROR);
        }

        try {
            $stmt = $this->db->prepare('SELECT invite FROM invites WHERE invite = :value AND status = \'0\'');

            $stmt->execute(['value' => $invite]);
        } catch (\PDOException $e) {
            return new ModelResponse($e->getMessage(), null, Action::SELECT, Status::FAILURE);
        }

        return $stmt->fetchColumn() ? true :
            new ModelResponse('Введенный Вами код инвайта  либо не существует, либо уже использован.', null, Action::VALIDATE, Status::ERROR);;
    }

    public function extractInvite(Request $request, $invite = null)
    {
        if (null === $invite) {
            $invite = $this->prepare($request->param('invite', 1));;
        }
        return (int)$invite;
    }

    /**
     * @param Request $request
     * @param null|int $invite
     * @return array|mixed
     */
    public function getInviteData(Request $request, $invite = null)
    {
        if (self::SELECT_ALL === $invite) {
            $stmt = $this->db->prepare('SELECT * FROM invites');
            $stmt->execute();

            return $stmt->fetchAll();
        } elseif (is_int($invite)) {
            //
        } else {
            $invite = $this->prepare($request->param('invite', 1));
        }

        $stmt = $this->db->prepare('SELECT * FROM invites WHERE invite = :value');
        $stmt->execute(['value' => $invite]);

        return $stmt->fetch();
    }

    /**
     * @return ModelResponse
     */
    public final function createSchema()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `invites` (
  `status` INTEGER DEFAULT 0,
  `invite` CHAR(6) NOT NULL UNIQUE,
  `date` INTEGER DEFAULT 0)';

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
        $sql = [];
        for ($i = 1; 150 >= $i; $i++) {
            $sql[] = '(\'' . \random_int(100000, 999999) . '\')';
        }

        $sql = implode(', ', $sql);

        try {
            $sql = 'INSERT OR IGNORE INTO `invites` (`invite`) VALUES ' . $sql;
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