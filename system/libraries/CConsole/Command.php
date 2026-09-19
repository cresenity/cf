<?php
use Illuminate\Contracts\Console\Isolatable;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class CConsole_Command extends SymfonyCommand {
    use CConsole_Trait_InteractsWithIOTrait;
    use CConsole_Trait_InteractsWithSignalsTrait;
    use CConsole_Trait_HasParametersTrait;
    use CConsole_Trait_CallsCommandsTrait;
    use CConsole_Trait_ConfiguresPromptsTrait;
    use CTrait_Macroable;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description;

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = false;

    /**
     * Indicates whether only one instance of the command can run at any given time.
     *
     * @var bool
     */
    protected $isolated = false;

    /**
     * The default exit code for isolated commands.
     *
     * @var int
     */
    protected $isolatedExitCode = self::SUCCESS;

    /**
     * The console command name aliases.
     *
     * @var array
     */
    protected $aliases;

    /**
     * Create a new console command instance.
     *
     * @return void
     */
    public function __construct() {
        // We will go ahead and set the name, description, and parameters on console
        // commands just to make things a little easier on the developer. This is
        // so they don't have to all be manually specified in the constructors.
        if (isset($this->signature)) {
            $this->configureUsingFluentDefinition();
        } else {
            parent::__construct($this->name);
        }

        // Once we have constructed the command, we'll set the description and other
        // related properties of the command. If a signature wasn't used to build
        // the command we'll set the arguments and the options on this command.
        $this->setDescription($this->description);

        $this->setHidden($this->hidden);

        if (isset($this->aliases)) {
            $this->setAliases((array) $this->aliases);
        }

        if (!isset($this->signature)) {
            $this->specifyParameters();
        }
        if ($this instanceof Isolatable) {
            $this->configureIsolation();
        }
    }

    /**
     * Configure the console command using a fluent definition.
     *
     * @return void
     */
    protected function configureUsingFluentDefinition() {
        list($name, $arguments, $options) = CConsole_Parser::parse($this->signature);

        parent::__construct($this->name = $name);

        // After parsing the signature we will spin through the arguments and options
        // and set them on this command. These will already be changed into proper
        // instances of these "InputArgument" and "InputOption" Symfony classes.
        $this->getDefinition()->addArguments($arguments);
        $this->getDefinition()->addOptions($options);
    }

    /**
     * Configure the console command for isolation.
     *
     * @return void
     */
    protected function configureIsolation() {
        $this->getDefinition()->addOption(new InputOption(
            'isolated',
            null,
            InputOption::VALUE_OPTIONAL,
            'Do not run the command if another instance of the command is already running',
            $this->isolated
        ));
    }

    /**
     * Run the console command.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    public function run(InputInterface $input, OutputInterface $output) {
        $this->output = $output instanceof CConsole_OutputStyle ? $output : c::container()->make(
            CConsole_OutputStyle::class,
            ['input' => $input, 'output' => $output]
        );
        $this->components = c::container()->make(CConsole_View_ComponentFactory::class, ['output' => $this->output]);

        try {
            $this->configurePrompts($input);
        } catch (Throwable $e) {
            // Prompts is a non-essential UX layer; never let a failure here
            // (e.g. an unsupported environment) break command execution.
        }

        try {
            return parent::run(
                $this->input = $input,
                $this->output
            );
        } finally {
            $this->untrap();
        }
    }

    /**
     * Execute the console command.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return mixed
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        return CContainer::getInstance()->call([$this, 'handle']);
    }

    /**
     * Get a command isolation mutex instance for the command.
     *
     * @return \CConsole_CommandMutexInterface
     */
    protected function commandIsolationMutex() {
        return c::container()->bound(CConsole_CommandMutexInterface::class)
            ? c::container()->make(CConsole_CommandMutexInterface::class)
            : c::container()->make(CConsole_CacheCommandMutex::class);
    }

    /**
     * Resolve the console command instance for the given command.
     *
     * @param \Symfony\Component\Console\Command\Command|string $command
     *
     * @return \Symfony\Component\Console\Command\Command
     */
    protected function resolveCommand($command) {
        if (!class_exists($command)) {
            return $this->getApplication()->find($command);
        }

        $command = c::container()->make($command);

        if ($command instanceof SymfonyCommand) {
            $command->setApplication($this->getApplication());
        }

        return $command;
    }

    /**
     * @inheritdoc
     *
     * @return bool
     */
    public function isHidden(): bool {
        return $this->hidden;
    }

    /**
     * @inheritdoc
     */
    public function setHidden($hidden = true) {
        parent::setHidden($this->hidden = $hidden);

        return $this;
    }
}
