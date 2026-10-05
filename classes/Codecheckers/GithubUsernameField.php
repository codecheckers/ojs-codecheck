<?php

/**
 * @file classes/Codecheckers/GithubUsernameField.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class GithubUsernameField
 *
 * @brief A GitHub username on the user's account (#13): what the register issue
 *   is assigned to when the user codechecks.
 *
 * Stored as the user property `githubUsername` in `user_settings`, as OJS stores
 * the user's ORCID iD, and edited where the rest of the account is: on the
 * user's public profile and in the manager's user form. Optional; a username
 * another account holds is refused, so an assignment on the register names one
 * account.
 */

namespace APP\plugins\generic\codecheck\classes\Codecheckers;

use APP\plugins\generic\codecheck\classes\Submission\CodecheckCodecheckers;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use Illuminate\Support\Facades\DB;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCustom;
use PKP\plugins\Hook;
use PKP\user\User;

class GithubUsernameField
{
    public const PROPERTY = 'githubUsername';

    /**
     * The forms that carry the field, by the lower-cased short class name
     * their hooks are named after.
     */
    private const FORMS = ['publicprofileform', 'userdetailsform'];

    /** Set in the form's data, so the template draws the field only where it is saved. */
    private const SHOWN = 'codecheckGithubUsernameField';

    public function __construct(private CodecheckPlugin $plugin)
    {
    }

    /** Adds the property, and the field and its checks to the two forms. */
    public function register(): void
    {
        Hook::add('Schema::get::user', $this->addToSchema(...));

        // `Form` lower-cases the whole name of these hooks, but only the class
        // name of the constructor's.
        foreach (self::FORMS as $form) {
            Hook::add($form . '::Constructor', $this->addChecks(...));
            Hook::add($form . '::initdata', $this->initData(...));
            Hook::add($form . '::readuservars', $this->readUserVars(...));
            Hook::add($form . '::execute', $this->execute(...));
        }

        Hook::add('User::PublicProfile::AdditionalItems', $this->render(...));
        Hook::add('Common::UserDetails::AdditionalItems', $this->render(...));
    }

    /** Declares the property on the user, as OJS declares its `orcid`. */
    public function addToSchema(string $hookName, array $args): bool
    {
        $schema = &$args[0];
        $schema->properties->{self::PROPERTY} = (object) [
            'type' => 'string',
            'validation' => ['nullable'],
        ];

        return false;
    }

    /**
     * The username rule from #186, and no other account holding it. Both are
     * skipped for an empty field: the username is optional.
     */
    public function addChecks(string $hookName, array $args): bool
    {
        $form = $args[0];

        $form->addCheck(new FormValidatorCustom(
            $form,
            self::PROPERTY,
            'optional',
            'plugins.generic.codecheck.githubUsername.invalid',
            fn ($value) => CodecheckCodecheckers::isGithubUsername($value)
        ));
        $form->addCheck(new FormValidatorCustom(
            $form,
            self::PROPERTY,
            'optional',
            'plugins.generic.codecheck.githubUsername.taken',
            fn ($value) => !self::isHeldByAnother(
                CodecheckCodecheckers::normalizeGithubUsername($value),
                self::findFormUserId($form)
            )
        ));

        return false;
    }

    /** Fills the field with the stored username, for an existing account. */
    public function initData(string $hookName, array $args): bool
    {
        $form = $args[0];
        $userId = self::findFormUserId($form);

        if ($userId !== null) {
            $form->setData(self::SHOWN, true);
            $form->setData(self::PROPERTY, self::readFor($userId));
        }

        return false;
    }

    /** Reads the field from a save, and keeps it shown when the save is refused. */
    public function readUserVars(string $hookName, array $args): bool
    {
        $form = $args[0];
        $vars = &$args[1];

        if (self::findFormUserId($form) !== null) {
            $vars[] = self::PROPERTY;
            $form->setData(self::SHOWN, true);
        }

        return false;
    }

    /** Stores the username a form saved, for an existing account. */
    public function execute(string $hookName, array $args): bool
    {
        $form = $args[0];
        $user = self::findFormUser($form);

        if ($user?->getId() && $form->getData(self::SHOWN)) {
            self::writeFor($user, $form->getData(self::PROPERTY));
        }

        return false;
    }

    /** Draws the field into a form that carries it. */
    public function render(string $hookName, array $args): bool
    {
        $smarty = $args[1];
        $output = &$args[2];

        if ($smarty->getTemplateVars(self::SHOWN)) {
            $output .= $smarty->fetch($this->plugin->getTemplateResource('user/githubUsername.tpl'));
        }

        return false;
    }

    /**
     * The username stored for a user.
     *
     * Read from the table rather than from a user object: the logged-in user
     * is read before plugins are loaded, against a schema without the
     * property, so it never carries it.
     */
    public static function readFor(int $userId): ?string
    {
        $username = DB::table('user_settings')
            ->where('user_id', $userId)
            ->where('setting_name', self::PROPERTY)
            ->value('setting_value');

        return is_string($username) && $username !== '' ? $username : null;
    }

    /**
     * Stores a user's username, normalised; an empty one removes it.
     *
     * Written to the table directly, for the reason readFor() gives: saved
     * through a user object read against the old schema, it would be
     * dropped. Set on the object too, so a later save of that object under
     * the full schema writes the same value rather than an older one.
     */
    public static function writeFor(User $user, mixed $username): void
    {
        $username = CodecheckCodecheckers::normalizeGithubUsername($username);
        $where = ['user_id' => (int) $user->getId(), 'locale' => '', 'setting_name' => self::PROPERTY];

        if ($username === '') {
            DB::table('user_settings')->where($where)->delete();
        } else {
            DB::table('user_settings')->updateOrInsert($where, ['setting_value' => $username]);
        }

        $user->setData(self::PROPERTY, $username === '' ? null : $username);
    }

    /**
     * Whether another account holds this username. GitHub's usernames are
     * case-insensitive, so the comparison is too.
     */
    public static function isHeldByAnother(string $username, ?int $userId): bool
    {
        if ($username === '') {
            return false;
        }

        return DB::table('user_settings')
            ->where('setting_name', self::PROPERTY)
            ->whereRaw('LOWER(setting_value) = ?', [strtolower($username)])
            ->when($userId !== null, fn ($query) => $query->where('user_id', '!=', $userId))
            ->exists();
    }

    /**
     * The user a form edits: the profile's own, or the manager form's, which
     * has none for a user it is about to create. The field is offered only for
     * an existing account, which a username can be stored for.
     */
    private static function findFormUser(Form $form): ?User
    {
        return method_exists($form, 'getUser') ? $form->getUser() : ($form->user ?? null);
    }

    private static function findFormUserId(Form $form): ?int
    {
        $userId = self::findFormUser($form)?->getId();

        return $userId ? (int) $userId : null;
    }
}
