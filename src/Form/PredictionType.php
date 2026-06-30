<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Game;
use App\Entity\Player;
use App\Entity\Team;
use App\Enum\Comparison;
use App\Enum\StatType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Dynamic, unmapped prediction form (UC-12).
 *
 * The "type" choice drives which fields are shown, and the form is scoped to a
 * single {@see Game}, so the team / player choices are restricted to that game.
 * The adaptation is performed server-side through form events:
 *  - PRE_SET_DATA: builds the fields for the initially requested type;
 *  - PRE_SUBMIT : builds the fields for the submitted type before binding.
 *
 * @extends AbstractType<mixed>
 */
class PredictionType extends AbstractType
{
    private const TYPES = [
        'Vainqueur du match' => 'match_winner',
        'Score exact' => 'score',
        "Performance d'un joueur" => 'player_prop',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $game = $options['game'];
        \assert($game instanceof Game);
        $predictionType = (string) $options['prediction_type'];

        $builder->add('type', ChoiceType::class, [
            'label' => 'Type de pronostic',
            'mapped' => false,
            'choices' => self::TYPES,
            'data' => $predictionType,
            'attr' => [
                // Switching the type reloads the page with the matching fields.
                'onchange' => "window.location.href='?type=' + this.value",
            ],
        ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($game, $predictionType): void {
            $this->addFieldsForType($event->getForm(), $predictionType, $game);
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($game): void {
            $data = $event->getData();
            $type = \is_array($data) && \is_string($data['type'] ?? null) ? $data['type'] : 'match_winner';
            $this->addFieldsForType($event->getForm(), $type, $game);
        });
    }

    private const DYNAMIC_FIELDS = [
        'predictedWinner',
        'predictedHomeScore',
        'predictedAwayScore',
        'player',
        'statType',
        'comparison',
        'predictedValue',
    ];

    /**
     * @param FormInterface<mixed> $form
     */
    private function addFieldsForType(FormInterface $form, string $type, Game $game): void
    {
        // Drop any field added for a previous type so PRE_SET_DATA (initial type)
        // and PRE_SUBMIT (submitted type) always converge on one coherent set.
        foreach (self::DYNAMIC_FIELDS as $field) {
            if ($form->has($field)) {
                $form->remove($field);
            }
        }

        switch ($type) {
            case 'score':
                $form
                    ->add('predictedHomeScore', IntegerType::class, [
                        'label' => sprintf('Score domicile (%s)', (string) $game->getHomeTeam()?->getName()),
                        'mapped' => false,
                        'constraints' => [new Assert\NotNull(), new Assert\Range(min: 0, max: 300)],
                    ])
                    ->add('predictedAwayScore', IntegerType::class, [
                        'label' => sprintf('Score extérieur (%s)', (string) $game->getAwayTeam()?->getName()),
                        'mapped' => false,
                        'constraints' => [new Assert\NotNull(), new Assert\Range(min: 0, max: 300)],
                    ]);
                break;

            case 'player_prop':
                $form
                    ->add('player', EntityType::class, [
                        'class' => Player::class,
                        'choices' => $this->playersOf($game),
                        'choice_label' => fn (Player $player): string => $player->getFullName(),
                        'label' => 'Joueur',
                        'mapped' => false,
                        'placeholder' => 'Choisir un joueur',
                        'constraints' => [new Assert\NotNull()],
                    ])
                    ->add('statType', EnumType::class, [
                        'class' => StatType::class,
                        'choice_label' => fn (StatType $stat): string => $stat->label(),
                        'label' => 'Statistique',
                        'mapped' => false,
                        'constraints' => [new Assert\NotNull()],
                    ])
                    ->add('comparison', EnumType::class, [
                        'class' => Comparison::class,
                        'choice_label' => fn (Comparison $comparison): string => $comparison->label(),
                        'label' => 'Sens',
                        'mapped' => false,
                        'expanded' => true,
                        'constraints' => [new Assert\NotNull()],
                    ])
                    ->add('predictedValue', NumberType::class, [
                        'label' => 'Seuil',
                        'mapped' => false,
                        'scale' => 1,
                        'constraints' => [new Assert\NotNull(), new Assert\PositiveOrZero()],
                    ]);
                break;

            case 'match_winner':
            default:
                $form->add('predictedWinner', EntityType::class, [
                    'class' => Team::class,
                    'choices' => array_values(array_filter([$game->getHomeTeam(), $game->getAwayTeam()])),
                    'choice_label' => 'name',
                    'label' => 'Vainqueur',
                    'mapped' => false,
                    'expanded' => true,
                    'constraints' => [new Assert\NotNull()],
                ]);
                break;
        }
    }

    /**
     * Players of both teams of the game.
     *
     * @return list<Player>
     */
    private function playersOf(Game $game): array
    {
        $players = [];
        foreach ([$game->getHomeTeam(), $game->getAwayTeam()] as $team) {
            if ($team !== null) {
                $players = array_merge($players, $team->getPlayers()->toArray());
            }
        }

        return $players;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'prediction_type' => 'match_winner',
        ]);
        $resolver->setRequired('game');
        $resolver->setAllowedTypes('game', Game::class);
        $resolver->setAllowedValues('prediction_type', ['match_winner', 'score', 'player_prop']);
    }
}
