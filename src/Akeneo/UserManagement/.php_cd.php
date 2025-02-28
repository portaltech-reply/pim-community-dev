<?php

use Akeneo\CouplingDetector\Configuration\Configuration;
use Akeneo\CouplingDetector\Configuration\DefaultFinder;
use Akeneo\CouplingDetector\RuleBuilder;

$finder = new DefaultFinder();

$builder = new RuleBuilder();

$rules = [
    $builder->only([
        'Symfony\Component',
        'Doctrine\Common',
        'Doctrine\Inflector',
        'Doctrine\Persistence',
        'Akeneo\Tool',
        'Webmozart\Assert\Assert',
        // TODO: The feature uses the datagrid
        'Oro\Bundle\PimDataGridBundle',
        //PIM-9806
        'Doctrine\ORM\Mapping',

        // TIP-945: User Management should not depend on Channel and Enrichment
        'Akeneo\Channel\Component\Model\LocaleInterface',
        'Akeneo\Channel\Component\Model\ChannelInterface',
        'Akeneo\Channel\Component\Repository\ChannelRepositoryInterface',
        'Akeneo\Channel\Component\Repository\LocaleRepositoryInterface',

        // TIP-944: UserManager used in component
        'Akeneo\UserManagement\Bundle\Manager\UserManager',

        // TODO: This dependency should be removed, Bundle dependency
        'Oro\Bundle\SecurityBundle\Acl\Persistence\AclManager',
        'Oro\Bundle\SecurityBundle\Acl\AccessLevel',
        'Oro\Bundle\SecurityBundle\Acl\Persistence\AclPrivilegeRepository',
        'Oro\Bundle\SecurityBundle\Model\AclPrivilege',
        'Oro\Bundle\SecurityBundle\SecurityFacade',

        'Oro\Bundle\UserBundle\Exception\UserCannotBeDeletedException',

        // TIP-947: UI Locale Provider should be part of UserManagement
        'Akeneo\Platform\Bundle\UIBundle\UiLocaleProvider'
    ])->in('Akeneo\UserManagement\Component'),
    $builder->only([
        'Doctrine',
        'Symfony\Component',
        'Symfony\Contracts',
        'Akeneo\Tool',
        'Akeneo\UserManagement',
        'Webmozart\Assert\Assert',
        'Oro\Bundle\SecurityBundle',
        'Sensio\Bundle\FrameworkExtraBundle',
        'Symfony\Bundle\FrameworkBundle',
        'Symfony\Bundle\SecurityBundle',
        'FOS\OAuthServerBundle\Entity\ClientManager', // used by API client controller
        'OAuth2\OAuth2', // used by API client controller
        'Swift_Mailer',
        'Twig\TwigFunction',
        'Oro\Bundle\DataGridBundle\Extension\Action\Actions\NavigateAction',
        'Akeneo\Platform\Bundle\FeatureFlagBundle\FeatureFlags',

        // TIP-1007: Clean VisibilityChecker system
        'Akeneo\Platform\Bundle\UIBundle\ViewElement\Checker\NonEmptyPropertyVisibilityChecker',

        // TIP-945: User Management should not depend on Channel and Enrichment
        'Akeneo\Channel\Component\Model\ChannelInterface',
        'Akeneo\Channel\Component\Model\Channel',
        'Akeneo\Channel\Component\Model\LocaleInterface',
        'Akeneo\Channel\Component\Model\Locale',
        'Akeneo\Channel\Component\Repository\ChannelRepositoryInterface',
        'Akeneo\Channel\Component\Repository\LocaleRepositoryInterface',
        'Akeneo\Pim\Enrichment\Component\Category\Model\CategoryInterface',

        // TIP-1005: Clean UI form types
        'Akeneo\Platform\Bundle\UIBundle\Form\Type\EntityIdentifierType',

        'Oro\Bundle\UserBundle\Exception\UserCannotBeDeletedException',

        // TIP-1539: clean installer events
        'Akeneo\Platform\Bundle\InstallerBundle\Event\InstallerEvent',
        'Akeneo\Platform\Bundle\InstallerBundle\Event\InstallerEvents',

        // PLG-692: use email notification from Notification bundle
        'Akeneo\Platform\Bundle\NotificationBundle\Email\MailNotifierInterface',
        'Twig\Environment',
        'Throwable',
        'Psr\Log\LoggerInterface',
    ])->in('Akeneo\UserManagement\Bundle'),
];

$config = new Configuration($rules, $finder);

return $config;
