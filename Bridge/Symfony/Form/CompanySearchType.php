<?php

namespace Omnistate\Bridge\Symfony\Form;

use Omnistate\Bridge\Symfony\CompanySearch;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * A company search field: type a name (or a SIREN), pick a company among the
 * register's, and the sibling fields named in `fill` take its details.
 *
 *     $builder->add('company', CompanySearchType::class, [
 *         'fill' => ['vatNumber' => 'vatNumber', 'companyName' => 'name', 'address' => 'address'],
 *     ]);
 *
 * `fill` maps a sibling field of the same form to a result key
 * (CompanySearch::row(): siren, siret, name, vatNumber, address, postalCode,
 * city, activity, legalForm...). Not mapped by default: it only helps fill
 * the others. The suggestions come from `endpoint` (by default the route
 * omnistate_company_search); the widget's script is in the form theme.
 */
final class CompanySearchType extends AbstractType
{
    public function __construct(private readonly ?UrlGeneratorInterface $urlGenerator = null)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mapped' => false,
            'required' => false,
            'endpoint' => null,
            'fill' => [],
            'min_length' => CompanySearch::MIN_LENGTH,
            'limit' => 10,
        ]);
        $resolver->setAllowedTypes('endpoint', ['null', 'string']);
        $resolver->setAllowedTypes('fill', 'array');
        $resolver->setAllowedTypes('min_length', 'int');
        $resolver->setAllowedTypes('limit', 'int');
    }

    public function getParent(): string
    {
        return SearchType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'omnistate_company_search';
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        // The sibling fields' element ids. Their views may not exist yet (those
        // declared after this one), so from the parent's id, as Symfony names
        // them: <parent id>_<name>.
        // configureOptions() lets through an array for fill, integers for
        // min_length and limit; Symfony gives every view its id and attr.
        /** @var array<string, string> $fill */
        $fill = $options['fill'];
        /** @var int $minLength */
        $minLength = $options['min_length'];
        /** @var int $limit */
        $limit = $options['limit'];
        /** @var array<string, mixed> $attr */
        $attr = $view->vars['attr'];
        $targets = [];
        $parent = $form->getParent();
        foreach ($fill as $field => $key) {
            if (null !== $parent && null !== $view->parent && $parent->has($field)) {
                /** @var string $id */
                $id = $view->parent->vars['id'];
                $targets[$id . '_' . $field] = $key;
            }
        }

        $view->vars['attr'] = array_merge($attr, [
            'autocomplete' => 'off',
            'data-omnistate-company-search' => $options['endpoint'] ?? $this->urlGenerator?->generate('omnistate_company_search') ?? '/omnistate/company/search',
            'data-omnistate-fill' => json_encode($targets, \JSON_THROW_ON_ERROR),
            'data-omnistate-min-length' => (string) $minLength,
            'data-omnistate-limit' => (string) $limit,
        ]);
    }
}
