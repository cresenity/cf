<?php

use PHPStan\Type\Type;
use PHPStan\TrinaryLogic;
use PHPStan\Type\ObjectType;
use PHPStan\Type\VerbosityLevel;
use PHPStan\ShouldNotHappenException;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\MissingMethodFromReflectionException;

class CQC_Phpstan_Service_BuilderHelper {
    /**
     * @var string[]
     */
    public const MODEL_RETRIEVAL_METHODS = ['first', 'find', 'findMany', 'findOrFail', 'firstOrFail', 'sole'];

    /**
     * @var string[]
     */
    public const MODEL_CREATION_METHODS = ['make', 'create', 'forceCreate', 'findOrNew', 'firstOrNew', 'updateOrCreate', 'firstOrCreate'];

    /**
     * Nama template model pada `CModel_Query` (`@template TModel of CModel`).
     *
     * @var string
     */
    const MODEL_TEMPLATE = 'TModel';

    /**
     * The methods that should be returned from query builder.
     *
     * @var string[]
     */
    public $passthru = [
        'average', 'avg',
        'count',
        'dd', 'dump',
        'doesntExist', 'exists',
        'getBindings', 'getConnection', 'getGrammar',
        'insert', 'insertGetId', 'insertOrIgnore', 'insertUsing',
        'max', 'min',
        'raw',
        'sum',
        'toSql',
    ];

    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    /**
     * @var bool
     */
    private $checkProperties;

    public function __construct(ReflectionProvider $reflectionProvider, bool $checkProperties) {
        $this->reflectionProvider = $reflectionProvider;
        $this->checkProperties = $checkProperties;
    }

    public function dynamicWhere(
        string $methodName,
        Type $returnObject
    ): ?CQC_Phpstan_Reflection_ModelQueryMethodReflection {
        if (!cstr::startsWith($methodName, 'where')) {
            return null;
        }

        if ($returnObject instanceof GenericObjectType && $this->checkProperties) {
            $returnClassReflection = $returnObject->getClassReflection();

            if ($returnClassReflection !== null) {
                $modelType = $returnClassReflection->getActiveTemplateTypeMap()->getType(static::MODEL_TEMPLATE);

                if ($modelType === null) {
                    $modelType = $returnClassReflection->getActiveTemplateTypeMap()->getType('TRelatedModel');
                }

                if ($modelType !== null) {
                    $finder = substr($methodName, 5);

                    $segments = preg_split(
                        '/(And|Or)(?=[A-Z])/',
                        $finder,
                        -1,
                        PREG_SPLIT_DELIM_CAPTURE
                    );

                    if ($segments !== false) {
                        $trinaryLogic = TrinaryLogic::createYes();

                        foreach ($segments as $segment) {
                            if ($segment !== 'And' && $segment !== 'Or') {
                                $trinaryLogic = $trinaryLogic->and($modelType->hasProperty(cstr::snake($segment)));
                            }
                        }

                        if (!$trinaryLogic->yes()) {
                            return null;
                        }
                    }
                }
            }
        }

        $classReflection = $this->reflectionProvider->getClass(CDatabase_Query_Builder::class);

        $methodReflection = $classReflection->getNativeMethod('dynamicWhere');

        return new CQC_Phpstan_Reflection_ModelQueryMethodReflection(
            $methodName,
            $classReflection,
            $methodReflection,
            [new CQC_Phpstan_Reflection_DynamicWhereParameterReflection()],
            $returnObject,
            true
        );
    }

    /**
     * This method mimics the `CModel_Query::__call` method.
     * Does not handle the case where $methodName exists in `CModel_Query`,
     * that should be checked by caller before calling this method.
     *
     * @param ClassReflection $eloquentBuilder can be `CModel_Query` or a custom builder extending it
     *
     * @throws MissingMethodFromReflectionException
     * @throws ShouldNotHappenException
     */
    public function searchOnModelQuery(ClassReflection $eloquentBuilder, string $methodName, ClassReflection $model): ?MethodReflection {
        // Check for local query scopes

        if (array_key_exists('scope' . ucfirst($methodName), $model->getMethodTags())) {
            $methodTag = $model->getMethodTags()['scope' . ucfirst($methodName)];

            $parameters = [];
            foreach ($methodTag->getParameters() as $parameterName => $parameterTag) {
                $parameters[] = new CQC_Phpstan_Reflection_AnnotationScopeMethodParameterReflection($parameterName, $parameterTag->getType(), $parameterTag->passedByReference(), $parameterTag->isOptional(), $parameterTag->isVariadic(), $parameterTag->getDefaultValue());
            }

            // We shift the parameters,
            // because first parameter is the Builder
            array_shift($parameters);

            return new CQC_Phpstan_Reflection_ModelQueryMethodReflection(
                'scope' . ucfirst($methodName),
                $model,
                new CQC_Phpstan_Reflection_AnnotationScopeMethodReflection('scope' . ucfirst($methodName), $model, $methodTag->getReturnType(), $parameters, $methodTag->isStatic(), false),
                $parameters,
                $methodTag->getReturnType()
            );
        }

        if ($model->hasNativeMethod('scope' . ucfirst($methodName))) {
            $methodReflection = $model->getNativeMethod('scope' . ucfirst($methodName));
            $parametersAcceptor = $methodReflection->getOnlyVariant();

            $parameters = $parametersAcceptor->getParameters();
            // We shift the parameters,
            // because first parameter is the Builder
            array_shift($parameters);

            $returnType = $parametersAcceptor->getReturnType();

            return new CQC_Phpstan_Reflection_ModelQueryMethodReflection(
                'scope' . ucfirst($methodName),
                $methodReflection->getDeclaringClass(),
                $methodReflection,
                $parameters,
                $returnType,
                $parametersAcceptor->isVariadic()
            );
        }

        $queryBuilderReflection = $this->reflectionProvider->getClass(CDatabase_Query_Builder::class);

        if (in_array($methodName, $this->passthru, true)) {
            return $queryBuilderReflection->getNativeMethod($methodName);
        }

        if ($queryBuilderReflection->hasNativeMethod($methodName)) {
            return $queryBuilderReflection->getNativeMethod($methodName);
        }

        return $this->dynamicWhere($methodName, new GenericObjectType($eloquentBuilder->getName(), [new ObjectType($model->getName())]));
    }

    /**
     * @throws MissingMethodFromReflectionException
     * @throws ShouldNotHappenException
     */
    public function determineBuilderName(string $modelClassName): string {
        $method = $this->reflectionProvider->getClass($modelClassName)->getNativeMethod('newModelQuery');

        $returnType = $method->getOnlyVariant()->getReturnType();

        if (in_array(CModel_Query::class, $returnType->getReferencedClasses(), true)) {
            return CModel_Query::class;
        }

        if ($returnType instanceof ObjectType) {
            return $returnType->getClassName();
        }

        return $returnType->describe(VerbosityLevel::value());
    }

    public function determineCollectionClassName(string $modelClassName): string {
        try {
            $newCollectionMethod = $this->reflectionProvider->getClass($modelClassName)->getNativeMethod('newCollection');
            $returnType = $newCollectionMethod->getOnlyVariant()->getReturnType();
            if ($returnType instanceof ObjectType) {
                return $returnType->getClassName();
            }

            return $returnType->describe(VerbosityLevel::value());
        } catch (MissingMethodFromReflectionException|ShouldNotHappenException $e) {
            return CCollection::class;
        }
    }
}
