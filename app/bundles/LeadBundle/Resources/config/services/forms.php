<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

//Lead forms
$container->setDefinition(
    'mautic.form.type.lead',
    new Definition(
        'Mautic\LeadBundle\Form\Type\LeadType',
        array(new Reference('mautic.factory'))
    )
)
    ->addTag('form.type', array(
        'alias' => 'lead',
    ));

//Lead list form
$container->setDefinition(
    'mautic.form.type.leadlist',
    new Definition(
        'Mautic\LeadBundle\Form\Type\ListType',
        array(new Reference('mautic.factory'))
    )
)
    ->addTag('form.type', array(
        'alias' => 'leadlist',
    ));


//Lead list filter form
$container->setDefinition(
    'mautic.form.type.leadlist_filters',
    new Definition(
        'Mautic\LeadBundle\Form\Type\FilterType')
)
    ->addTag('form.type', array(
        'alias' => 'leadlist_filters',
    ));


//Lead field form
$container->setDefinition(
    'mautic.form.type.leadfield',
    new Definition(
        'Mautic\LeadBundle\Form\Type\FieldType',
        array(new Reference('mautic.factory'))
    )
)
    ->addTag('form.type', array(
        'alias' => 'leadfield',
    ));


//Form submit action forms
$container->setDefinition(
    'mautic.form.type.lead.submitaction.createlead',
    new Definition(
        'Mautic\LeadBundle\Form\Type\FormSubmitActionCreateLeadType'
    )
)
    ->addTag('form.type', array(
        'alias' => 'lead_submitaction_createlead',
    ));

$container->setDefinition(
    'mautic.form.type.lead.submitaction.mappedfields',
    new Definition(
        'Mautic\LeadBundle\Form\Type\FormSubmitActionMappedFieldsType',
        array(new Reference('mautic.factory'))
    )
)
    ->addTag('form.type', array(
        'alias' => 'lead_submitaction_mappedfields',
    ));


$container->setDefinition(
    'mautic.form.type.lead.submitaction.scorechange',
    new Definition(
        'Mautic\LeadBundle\Form\Type\FormSubmitActionScoreChangeType',
        array(new Reference('mautic.factory'))
    )
)
    ->addTag('form.type', array(
        'alias' => 'lead_submitaction_scorechange',
    ));