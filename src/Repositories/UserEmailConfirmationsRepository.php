<?php

namespace Crm\UsersModule\Repositories;

use Crm\ApplicationModule\Models\Database\ActiveRow;
use Crm\ApplicationModule\Models\Database\Repository;
use Crm\UsersModule\Models\Auth\Access\TokenGenerator;
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
        $emailConfirmationRow = $this->getTable()->where('token', $token)->order('id DESC')->fetch();
        if (!$emailConfirmationRow) {
            return null;
        }

        if ($emailConfirmationRow->confirmed_at === null) {
            $this->update($emailConfirmationRow, ['confirmed_at' => new DateTime()]);
        }

        /** @var ActiveRow $emailConfirmationRow */
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
