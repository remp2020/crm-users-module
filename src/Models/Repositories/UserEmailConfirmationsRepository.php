<?php

namespace Crm\UsersModule\Repository;

use Crm\ApplicationModule\ActiveRow;
use Crm\ApplicationModule\Repository;
use Crm\UsersModule\Auth\Access\TokenGenerator;
use Nette\Utils\DateTime;

class UserEmailConfirmationsRepository extends Repository
{
    protected $tableName = 'user_email_confirmations';

    public function generate(int $userId)
    {
        return $this->insert([
            'user_id' => $userId,
            'token' => TokenGenerator::generate(32),
        ]);
    }

    public function confirm(string $token): ?ActiveRow
    {
        /** @var ActiveRow $emailConfirmationRow */
        $emailConfirmationRow = $this->getTable()->where('token', $token)->order('id DESC')->fetch();
        if (!$emailConfirmationRow) {
            return null;
        }

        if ($emailConfirmationRow->confirmed_at === null) {
            $this->update($emailConfirmationRow, ['confirmed_at' => new DateTime()]);
        }

        return $emailConfirmationRow;
    }

    public function isConfirmed(int $userId):bool
    {
        return $this->getTable()
            ->where('user_id', $userId)
            ->where('confirmed_at IS NOT NULL')
            ->count('*') > 0;
    }

    public function getToken(int $userId): ?string
    {
        return $this->getTable()
            ->where('user_id', $userId)
            ->order('id DESC')
            ->fetchField('token');
    }
}
