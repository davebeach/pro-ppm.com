<?php

namespace Drupal\entity_clone\Form;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\Entity;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\entity_clone\Events\EntityCloneEvent;
use Drupal\entity_clone\Events\EntityCloneEvents;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Implements an entity Clone form.
 */
class EntityCloneForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The entity ready to clone.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The entity type définition.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityTypeDefinition;

  /**
   * The string translation manager.
   *
   * @var \Drupal\Core\StringTranslation\TranslationManager
   */
  protected $stringTranslationManager;

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a new Entity Clone form.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param \Drupal\Core\StringTranslation\TranslationManager $string_translation
   *   The string translation manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher service.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManager $entity_type_manager, RouteMatchInterface $route_match, TranslationManager $string_translation, EventDispatcherInterface $eventDispatcher) {
    $this->entityTypeManager = $entity_type_manager;
    $this->stringTranslationManager = $string_translation;
    $this->eventDispatcher = $eventDispatcher;

    $parameter_name = $route_match->getRouteObject()->getOption('_entity_clone_entity_type_id');
    $this->entity = $route_match->getParameter($parameter_name);

    $this->entityTypeDefinition = $entity_type_manager->getDefinition($this->entity->getEntityTypeId());
  }


  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('string_translation'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_clone_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if ($this->entity && $this->entityTypeDefinition->hasHandlerClass('entity_clone')) {
      $form['information'] = [
        '#markup' => $this->stringTranslationManager->translate('<p>Do you want clone the <em>@entity_type</em> entity named <em>@title</em>?</p>', [
          '@entity_type' => $this->entity->getEntityType()->getLabel(),
          '@title' => $this->entity->label(),
        ]),
      ];

      /** @var \Drupal\entity_clone\EntityClone\EntityCloneFormInterface $entity_clone_handler */
      if ($this->entityTypeManager->hasHandler($this->entityTypeDefinition->id(), 'entity_clone_form')) {
        $entity_clone_form_handler = $this->entityTypeManager->getHandler($this->entityTypeDefinition->id(), 'entity_clone_form');
        $form = array_merge($form, $entity_clone_form_handler->formElement($this->entity));
      }

      $form['clone'] = [
        '#type' => 'submit',
        '#value' => 'Clone',
      ];

      $form['abort'] = [
        '#type' => 'submit',
        '#value' => 'Abort',
        '#submit' => array('::cancelForm'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\entity_clone\EntityClone\EntityCloneInterface $entity_clone_handler */
    $entity_clone_handler = $this->entityTypeManager->getHandler($this->entityTypeDefinition->id(), 'entity_clone');
    if ($this->entityTypeManager->hasHandler($this->entityTypeDefinition->id(), 'entity_clone_form')) {
      $entity_clone_form_handler = $this->entityTypeManager->getHandler($this->entityTypeDefinition->id(), 'entity_clone_form');
    }

    $properties = [];
    if (isset($entity_clone_form_handler) && $entity_clone_form_handler) {
      $properties = $entity_clone_form_handler->getNewValues($form_state);
    }

    $duplicate = $this->entity->createDuplicate();

    $this->eventDispatcher->dispatch(EntityCloneEvents::PRE_CLONE, new EntityCloneEvent($this->entity, $duplicate, $properties));
    $cloned_entity = $entity_clone_handler->cloneEntity($this->entity, $duplicate, $properties);
    $this->eventDispatcher->dispatch(EntityCloneEvents::POST_CLONE, new EntityCloneEvent($this->entity, $duplicate, $properties));

    drupal_set_message($this->stringTranslationManager->translate('The entity <em>@entity (@entity_id)</em> of type <em>@type</em> was cloned', [
      '@entity' => $this->entity->label(),
      '@entity_id' => $this->entity->id(),
      '@type' => $this->entity->getEntityTypeId(),
    ]));

    if ($cloned_entity && $cloned_entity->hasLinkTemplate('edit-form')) {
      $form_state->setRedirect($cloned_entity->toUrl('edit-form')
        ->getRouteName(), $cloned_entity->toUrl('edit-form')->getRouteParameters());
    }
    else {
      $form_state->setRedirect('<front>');
    }
  }

  /**
   * Cancel form handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function cancelForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('<front>');
  }

}
