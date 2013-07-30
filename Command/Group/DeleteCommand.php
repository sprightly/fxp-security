<?php

/*
 * This file is part of the Sonatra package.
 *
 * (c) François Pluchino <francois.pluchino@sonatra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonatra\Bundle\SecurityBundle\Command\Group;

use Sonatra\Bundle\SecurityBundle\Command\DeleteCommand as BaseDeleteCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author François Pluchino <francois.pluchino@sonatra.com>
 */
class DeleteCommand extends BaseDeleteCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('security:group:delete')
            ->setDescription('Delete a group');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $entityClass = $this->getContainer()->getParameter('sonatra_security.group_class');
        $entityName = $input->getArgument('name');
        $filter = array('name' => $entityName);

        $this->doExecute($output, $entityClass, $entityName, $filter);
    }
}
