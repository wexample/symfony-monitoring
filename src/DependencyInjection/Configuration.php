<?php

namespace Wexample\SymfonyMonitoring\DependencyInjection;

use DateInterval;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Wexample\SymfonyCheck\Enum\Severity;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('wexample_symfony_monitoring');
        $severities = array_map(static fn (Severity $severity): string => $severity->value, Severity::cases());

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('alerts')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('roles')
                            ->info('Who is told when an incident opens or comes back: the users of these roles. Empty: nobody.')
                            ->scalarPrototype()->end()
                            ->defaultValue(['ROLE_ADMIN'])
                        ->end()
                        ->enumNode('min_severity')
                            ->info('The least severe incident told.')
                            ->values($severities)
                            ->defaultValue(Severity::Warning->value)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('heartbeats')
                    ->info('What must keep running, by key: beaten with HeartbeatService::beat(), an incident when it misses its interval.')
                    ->useAttributeAsKey('key')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('label')->defaultNull()->end()
                            ->scalarNode('every')
                                ->info('How often it must succeed at least: `26 hours`, `1 week`.')
                                ->isRequired()
                                ->validate()
                                    ->ifTrue(static fn (string $every): bool => false === @DateInterval::createFromDateString($every))
                                    ->thenInvalid('%s is no interval.')
                                ->end()
                            ->end()
                            ->enumNode('severity')->values($severities)->defaultValue(Severity::Error->value)->end()
                        ->end()
                    ->end()
                ->end()
                ->integerNode('retention_days')
                    ->info('Closed incidents — resolved, ignored — are deleted after this many days; null keeps them.')
                    ->defaultValue(180)
                    ->min(1)
                ->end()
            ->end();

        return $treeBuilder;
    }
}
