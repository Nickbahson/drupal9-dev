<?php

namespace Drupal\og_addition\Plugin\EntityViewBuilder;

use Drupal\Core\Url;
use Drupal\intl_date\IntlDate;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\server_general\EntityDateTrait;
use Drupal\server_general\EntityViewBuilder\NodeViewBuilderAbstract;
use Drupal\server_general\TitleAndLabelsTrait;

/**
 * The "Node Group" plugin.
 *
 * @EntityViewBuilder(
 *   id="node.group",
 *   label=@Translation("Node - Group"),
 *   description = "Node view builder for Group bundle."
 * )
 */
class NodeGroup extends NodeViewBuilderAbstract {
  use EntityDateTrait;
  use TitleAndLabelsTrait;

  /**
   * Build full view mode.
   *
   * @param array $build
   *   The existing build.
   * @param \Drupal\node\NodeInterface $entity
   *   The entity.
   *
   * @return array
   *   Render array.
   */
  public function buildFull(array $build, NodeInterface $entity): array {

    $elements = [];

    $user = $this->currentUser;
    $storage = $this->entityTypeManager->getStorage('og_membership');
    $props = [
      'uid' => $user ? $user->id() : 0,
      'entity_type' => $entity->getEntityTypeId(),
      'entity_bundle' => $entity->bundle(),
      'entity_id' => $entity->id(),
    ];
    $memberships = $storage->loadByProperties($props);

    $parameters = [
      'entity_type_id' => $entity->getEntityTypeId(),
      'group' => $entity->id(),
    ];

    if (!$memberships) {
      $route_name = 'og.subscribe';
      $text = "Hi @name, click here if you would like to subscribe to this group called @group_name.";
      $parameters['og_membership_type'] = OgMembershipInterface::TYPE_DEFAULT;
      $attr_class = ['subscribe'];
    }
    else {
      // Member is pending or active.
      $route_name = 'og.unsubscribe';
      $text = "Hi @name, you're already subscribed to this group called, @group_name,
      click here if you would like to unsubscribe.";
      $attr_class = ['unsubscribe'];
    }

    $url = Url::fromRoute($route_name, $parameters);

    $group_name = $entity->label();
    $name = $user->getDisplayName();

    $title = $this->t($text, [
      '@name' => $name,
      '@group_name' => $group_name,
    ]);

    $og_link = [
      '#type' => 'link',
      '#cache' => [
        'max-age' => 0,
      ],
      '#title' => $title,
      '#url' => $url,
      '#attributes' => [
        'class' => $attr_class,
        'id' => ['og_group'],
      ],
    ];

    // Header.
    $header = $this->buildHeader($entity);
    $elements[] = $this->wrapContainerWide($header);

    // Body.
    $body = $this->buildProcessedText($entity, 'body');
    $elements[] = $this->wrapContainerWide($body);

    // Un|Subscribe link.
    $elements[] = $this->wrapContainerWide($og_link);
    $build[] = $this->wrapContainerBottomPadding($elements);

    return $build;
  }

  /**
   * Build the header.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The entity.
   *
   * @return array
   *   Render array
   *
   * @throws \IntlException
   */
  protected function buildHeader(NodeInterface $entity): array {
    $elements = [];

    $elements[] = $this->buildConditionalPageTitle($entity);

    // Show the node type as a label.
    $node_type = NodeType::load($entity->bundle());
    $elements[] = $this->buildLabelsFromText([$node_type->label()]);

    // Date.
    $timestamp = $this->getFieldOrCreatedTimestamp($entity, 'field_publish_date');
    $element = IntlDate::formatPattern($timestamp, 'long');

    // Make text bigger.
    $elements[] = $this->wrapTextDecorations($element, FALSE, FALSE, 'lg');

    $elements = $this->wrapContainerVerticalSpacing($elements);
    return $this->wrapContainerNarrow($elements);
  }

}
