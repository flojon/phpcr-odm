<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ODM\PHPCR\Tools\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use PHPCR\Util\Console\Command\RegisterNodeTypesCommand;

use Doctrine\ODM\PHPCR\Translation\Translation;

/**
 * Command to register the phcpr-odm required node types.
 *
 * This command registers the necessary node types to get phpcr odm working
 */
class RegisterSystemNodeTypesCommand extends RegisterNodeTypesCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
        ->setName('doctrine:phpcr:register-system-node-types')
        ->setDescription('Register system node types in the PHPCR repository')
        ->setHelp(<<<EOT
Register system node types in the PHPCR repository.

This command registers the node types necessary for the ODM to work.
EOT
        );
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $phpcrNamespace = 'phpcr';
        $phpcrNamespaceUri = 'http://www.doctrine-project.org/projects/phpcr_odm';
        $localeNamespace = Translation::LOCALE_NAMESPACE;
        $localeNamespaceUri = Translation::LOCALE_NAMESPACE_URI;

        /** @var $session \PHPCR\SessionInterface */
        $session = $this->getHelper('phpcr')->getSession();
        if ($session instanceof \Jackalope\Session
            && $session->getTransport() instanceof \Jackalope\Transport\Jackrabbit\Client
        ) {
            $cnd = <<<CND
// register phpcr_locale namespace
<$localeNamespace='$localeNamespaceUri'>
// register phpcr namespace
<$phpcrNamespace='$phpcrNamespaceUri'>
[phpcr:managed]
  mixin
  - phpcr:class (STRING)
CND
            ;
            // automatically overwrite - we are inside our phpcr namespace, nothing can go wrong
            $this->updateFromCnd($input, $output, $session, $cnd, true);
        } else {
            $ns = $session->getWorkspace()->getNamespaceRegistry();
            $ns->registerNamespace($phpcrNamespace, $phpcrNamespaceUri);
            $ns->registerNamespace($localeNamespace, $localeNamespaceUri);
            $nt = $session->getWorkspace()->getNodeTypeManager();
            $proptpl = $nt->createPropertyDefinitionTemplate();
            $proptpl->setName('phpcr:class');
            $proptpl->setRequiredType(\PHPCR\PropertyType::STRING);
            $tpl = $nt->createNodeTypeTemplate();
            $tpl->setName('phpcr:managed');
            $tpl->setMixin(true);
            $props = $tpl->getPropertyDefinitionTemplates();
            $props->offsetSet(null, $proptpl);
            $nt->registerNodeType($tpl, true);
        }
        $output->write(PHP_EOL.sprintf('Successfully registered system node types.') . PHP_EOL);
    }
}
