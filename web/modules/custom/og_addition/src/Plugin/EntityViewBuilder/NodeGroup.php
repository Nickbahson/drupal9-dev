<?php

namespace Drupal\og_addition\Plugin\EntityViewBuilder;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\intl_date\IntlDate;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\og\OgAccessInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\og\MembershipManagerInterface;
use Drupal\server_general\EntityDateTrait;
use Drupal\server_general\EntityViewBuilder\NodeViewBuilderAbstract;
use Drupal\server_general\TitleAndLabelsTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
  use RedirectDestinationTrait;

  /**
   * The OG access service.
   *
   * @var \Drupal\og\OgAccessInterface
   */
  protected $ogAccess;

  /**
   * The OG membership_manager service.
   *
   * @var \Drupal\og\MembershipManagerInterface
   */
  protected $ogMembershipManager;

  /**
   * Constructs a new NodeGroup view plugin object.
   *
   * @param array $configuration
   *   The configuration associated with this plugin.
   * @param string $plugin_id
   *   The plugin_id.
   * @param mixed $plugin_definition
   *   The definition of the plugin.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\og\OgAccessInterface $og_access
   *   The OG access service.
   * @param \Drupal\og\MembershipManagerInterface $og_membership_manager
   *   The OG membership_manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user, OgAccessInterface $og_access, MembershipManagerInterface $og_membership_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $current_user);
    $this->ogAccess = $og_access;
    $this->ogMembershipManager = $og_membership_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('og.access'),
      $container->get('og.membership_manager'),
    );
  }

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

    // Header.
    $header = $this->buildHeader($entity);
    $elements[] = $this->wrapContainerWide($header);

    // Body.
    $body = $this->buildProcessedText($entity, 'body');
    $elements[] = $this->wrapContainerWide($body);

    // Compute og message/links/text.
    $elements[] = $this->buildOgMarkup($entity);

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

  /**
   * Builds the OG output.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The entity.
   *
   * @return array
   *   Render array
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function buildOgMarkup(NodeInterface $entity) {
    $user = $this->currentUser;

    // Auto group owners/creator.
    if ($entity->getOwnerId() == $user->id()) {
      $og_item = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => [
          'title' => $this->t('You are the group manager'),
          'class' => ['group', 'manager'],
        ],
        '#value' => $this->t('You are the group manager'),
      ];

      return $this->wrapContainerWide($og_item);
    }

    $options = [];
    $attr_class = [];
    $text = "";
    $group_name = $entity->label();
    $name = $user->getDisplayName();
    $parameters = [
      'entity_type_id' => $entity->getEntityTypeId(),
      'group' => $entity->id(),
    ];

    $active_sub_state = [
      OgMembershipInterface::STATE_ACTIVE,
      OgMembershipInterface::STATE_PENDING,
    ];

    $membership = $this->ogMembershipManager->getMembership($entity, $user->id(), $active_sub_state);

    if ($membership) {
      if ($membership->isBlocked()) {
        // If user is blocked, they should not be able to apply for
        // membership.
        return [];
      }

      // Member is pending or active.
      $route_name = 'og.unsubscribe';
      $text = "Hi @name, you're already subscribed to this group called, @group_name,
          click here if you would like to unsubscribe.";
      $title = $this->t($text, ['@name' => $name, '@group_name' => $group_name]);
      $attr_class = ['unsubscribe'];
      $url = Url::fromRoute($route_name, $parameters, $options);

      // Allow them to unsubscribe.
      $og_item = [
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
      // Let the user unsubscribe.
      return $this->wrapContainerWide($og_item);

    }
    else {

      // Below users can either subscribe or don't have permission/access to.
      // If the user is authenticated, set up the subscribe link.
      if ($user->isAuthenticated()) {
        $route_name = 'og.subscribe';
        $parameters['og_membership_type'] = OgMembershipInterface::TYPE_DEFAULT;

      }
      else {
        // User is anonymous, link to user login and redirect back to here.
        $route_name = 'user.login';
        $parameters = [];
        $options = ['query' => $this->getDestinationArray()];
      }

    }

    $url = Url::fromRoute($route_name, $parameters, $options);

    // Access control, custom subscribe, unsubscribe messages.
    /** @var \Drupal\Core\Access\AccessResult $access */
    if (($access = $this->ogAccess->userAccess($entity, 'subscribe without approval', $user)) && $access->isAllowed()) {
      if (!$user->isAuthenticated()) {
        $title = $this->t('Please login to request group membership');
      }
      else {
        $text = "Hi @name, click here if you would like to subscribe to this group called @group_name.";
        $title = $this->t($text, [
          '@name' => $name,
          '@group_name' => $group_name,
        ]);

        $attr_class = ['subscribe'];
      }

    }
    elseif (($access = $this->ogAccess->userAccess($entity, 'subscribe', $user)) && $access->isAllowed()) {

      // Unauthenticated users should see 'Request group membership'.
      // Logged in users should be greeted with their name,
      // to join group (labelled group name).
      if (!$user->isAuthenticated()) {
        $title = $this->t('Please login to request group membership');
      }
      else {
        $text = "Hi $name, click here if you would like to subscribe to this group called $group_name.";
        $title = $this->t($text, [
          '@name' => $name,
          '@group_name' => $group_name,
        ]);
      }
      $attr_class = ['subscribe', 'request'];

    }
    else {
      // No subscription option.
      $og_item = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => [
          'title' => $this->t('This is a closed group. Only a group administrator can add you.'),
          'class' => ['group', 'closed'],
        ],
        '#value' => $this->t('This is a closed group. Only a group administrator can add you.'),
      ];
      return $this->wrapContainerWide($og_item);
    }

    $og_item = [
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
    return $this->wrapContainerWide($og_item);
  }

}
