<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class RouletteBetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('betType', ChoiceType::class, [
                'label' => 'Typ zakładu',
                'choices' => [
                    'Numer (0–36)' => 'number',
                    'Kolor (red/black)' => 'color',
                    'Parzyste/Nieparzyste (even/odd)' => 'even',
                ],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Choice(['number', 'color', 'even']),
                ],
            ])
            ->add('betValue', TextType::class, [
                'label' => 'Wartość',
                'help' => 'number: 0-36 | color: red/black | even: even/odd',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => 20]),
                ],
            ])
            ->add('amount', IntegerType::class, [
                'label' => 'Kwota',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Positive(),
                ],
            ])
        ;
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'constraints' => [
                new Callback([$this, 'validateBet']),
            ],
        ]);
    }
    public function validateBet(mixed $data, ExecutionContextInterface $context): void
    {
        if (!is_array($data)) {
            return;
        }

        $type = (string)($data['betType'] ?? '');
        $value = strtolower(trim((string)($data['betValue'] ?? '')));

        if ($type === 'number') {
            if (!ctype_digit($value)) {
                $context->buildViolation('Dla typu "number" podaj liczbę 0–36.')
                    ->atPath('betValue')
                    ->addViolation();
                return;
            }

            $num = (int) $value;
            if ($num < 0 || $num > 36) {
                $context->buildViolation('Numer musi być w zakresie 0–36.')
                    ->atPath('betValue')
                    ->addViolation();
            }

            return;
        }

        if ($type === 'color') {
            if (!in_array($value, ['red', 'black'], true)) {
                $context->buildViolation('Dla typu "color" wpisz: red lub black.')
                    ->atPath('betValue')
                    ->addViolation();
            }
            return;
        }

        if ($type === 'even') {
            if (!in_array($value, ['even', 'odd'], true)) {
                $context->buildViolation('Dla typu "even" wpisz: even lub odd.')
                    ->atPath('betValue')
                    ->addViolation();
            }
            return;
        }

        $context->buildViolation('Nieznany typ zakładu.')
            ->atPath('betType')
            ->addViolation();
    }
}
