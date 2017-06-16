<?php

/*
 * This file is part of the Sonatra package.
 *
 * (c) François Pluchino <francois.pluchino@sonatra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonatra\Component\Security\Tests\Validator\Constraints;

use PHPUnit\Framework\TestCase;
use Sonatra\Component\Security\Identity\SubjectIdentity;
use Sonatra\Component\Security\Sharing\SharingIdentityConfig;
use Sonatra\Component\Security\Sharing\SharingManagerInterface;
use Sonatra\Component\Security\Tests\Fixtures\Model\MockObject;
use Sonatra\Component\Security\Tests\Fixtures\Model\MockPermission;
use Sonatra\Component\Security\Tests\Fixtures\Model\MockRole;
use Sonatra\Component\Security\Tests\Fixtures\Model\MockSharing;
use Sonatra\Component\Security\Validator\Constraints\PermissionValidator;
use Sonatra\Component\Security\Validator\Constraints\Sharing;
use Sonatra\Component\Security\Validator\Constraints\SharingValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * @author François Pluchino <francois.pluchino@sonatra.com>
 */
class SharingValidatorTest extends TestCase
{
    /**
     * @var SharingManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $sharingManager;

    /**
     * @var ExecutionContextInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $context;

    /**
     * @var PermissionValidator
     */
    protected $validator;

    protected function setUp()
    {
        $this->sharingManager = $this->getMockBuilder(SharingManagerInterface::class)->getMock();
        $this->context = $this->getMockBuilder(ExecutionContextInterface::class)->getMock();
        $this->validator = new SharingValidator($this->sharingManager);
    }

    public function testValidateWithEmptyFields()
    {
        $constraint = new Sharing();
        $sharing = new MockSharing();

        $this->addViolation(0, 'sharing.class.invalid', 'subjectClass', array(
            '%class_property%' => 'subjectClass',
            '%class%' => null,
        ));

        $this->addViolation(1, 'sharing.class.invalid', 'identityClass', array(
            '%class_property%' => 'identityClass',
            '%class%' => null,
        ));

        $this->validator->initialize($this->context);
        $this->validator->validate($sharing, $constraint);
    }

    public function testValidateWithNotManagedClass()
    {
        $constraint = new Sharing();
        $sharing = new MockSharing();
        $sharing->setSubjectClass(MockObject::class);
        $sharing->setIdentityClass(MockRole::class);

        $this->sharingManager->expects($this->at(0))
            ->method('hasSharingVisibility')
            ->with(SubjectIdentity::fromClassname(MockObject::class))
            ->willReturn(false);

        $this->addViolation(0, 'sharing.class.not_managed', 'subjectClass', array(
            '%class_property%' => 'subjectClass',
            '%class%' => MockObject::class,
        ));

        $this->sharingManager->expects($this->at(1))
            ->method('hasIdentityConfig')
            ->with(MockRole::class)
            ->willReturn(false);

        $this->addViolation(1, 'sharing.class.not_managed', 'identityClass', array(
            '%class_property%' => 'identityClass',
            '%class%' => MockRole::class,
        ));

        $this->validator->initialize($this->context);
        $this->validator->validate($sharing, $constraint);
    }

    public function testValidateFieldWithInvalidRole()
    {
        $constraint = new Sharing();
        $sharing = new MockSharing();
        $sharing->setSubjectClass(MockObject::class);
        $sharing->setIdentityClass(MockRole::class);
        $sharing->setRoles(array('ROLE_TEST'));

        $this->sharingManager->expects($this->at(0))
            ->method('hasSharingVisibility')
            ->with(SubjectIdentity::fromClassname(MockObject::class))
            ->willReturn(true);

        $this->sharingManager->expects($this->at(1))
            ->method('hasIdentityConfig')
            ->with(MockRole::class)
            ->willReturn(true);

        $config = new SharingIdentityConfig(MockRole::class);

        $this->sharingManager->expects($this->at(2))
            ->method('getIdentityConfig')
            ->with(MockRole::class)
            ->willReturn($config);

        $this->addViolation(0, 'sharing.class.identity_not_roleable', 'roles', array(
            '%class_property%' => 'identityClass',
            '%class%' => MockRole::class,
        ));

        $this->validator->initialize($this->context);
        $this->validator->validate($sharing, $constraint);
    }

    public function testValidateFieldWithInvalidPermission()
    {
        $constraint = new Sharing();
        $sharing = new MockSharing();
        $sharing->setSubjectClass(MockObject::class);
        $sharing->setIdentityClass(MockRole::class);
        $sharing->getPermissions()->add(new MockPermission());

        $this->sharingManager->expects($this->at(0))
            ->method('hasSharingVisibility')
            ->with(SubjectIdentity::fromClassname(MockObject::class))
            ->willReturn(true);

        $this->sharingManager->expects($this->at(1))
            ->method('hasIdentityConfig')
            ->with(MockRole::class)
            ->willReturn(true);

        $config = new SharingIdentityConfig(MockRole::class);

        $this->sharingManager->expects($this->at(2))
            ->method('getIdentityConfig')
            ->with(MockRole::class)
            ->willReturn($config);

        $this->addViolation(0, 'sharing.class.identity_not_permissible', 'permissions', array(
            '%class_property%' => 'identityClass',
            '%class%' => MockRole::class,
        ));

        $this->validator->initialize($this->context);
        $this->validator->validate($sharing, $constraint);
    }

    public function testValidate()
    {
        $constraint = new Sharing();
        $sharing = new MockSharing();
        $sharing->setSubjectClass(MockObject::class);
        $sharing->setIdentityClass(MockRole::class);
        $sharing->setRoles(array('ROLE_TEST'));
        $sharing->getPermissions()->add(new MockPermission());

        $this->sharingManager->expects($this->at(0))
            ->method('hasSharingVisibility')
            ->with(SubjectIdentity::fromClassname(MockObject::class))
            ->willReturn(true);

        $this->sharingManager->expects($this->at(1))
            ->method('hasIdentityConfig')
            ->with(MockRole::class)
            ->willReturn(true);

        $config = new SharingIdentityConfig(MockRole::class, 'role', true, true);

        $this->sharingManager->expects($this->at(2))
            ->method('getIdentityConfig')
            ->with(MockRole::class)
            ->willReturn($config);

        $this->validator->initialize($this->context);
        $this->validator->validate($sharing, $constraint);
    }

    /**
     * Add violation.
     *
     * @param int    $position   The position
     * @param string $message    The message
     * @param string $path       The property path
     * @param array  $parameters The violation parameters
     */
    protected function addViolation($position, $message, $path, array $parameters = array())
    {
        $vb = $this->getMockBuilder(ConstraintViolationBuilderInterface::class)->getMock();
        $i = 0;

        $this->context->expects($this->at($position))
            ->method('buildViolation')
            ->with($message)
            ->willReturn($vb);

        $vb->expects($this->at(0))
            ->method('atPath')
            ->with($path)
            ->willReturn($vb);

        foreach ($parameters as $key => $value) {
            ++$i;
            $vb->expects($this->at($i))
                ->method('setParameter')
                ->with($key, $value)
                ->willReturn($vb);
        }

        $vb->expects($this->once())
            ->method('addViolation');
    }
}
