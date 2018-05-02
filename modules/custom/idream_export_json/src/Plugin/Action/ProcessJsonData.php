<?php

namespace Drupal\idream_export_json\Plugin\Action;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\idream_export_json\Controller;

/**
 * Visualizes an entity.
 *
 * @Action(
 *   id = "entity:process_json_data",
 *   label = @Translation("Visualize"),
 *   type = "node"
 * )
 */
class ProcessJsonData extends ActionBase {
  public function __construct() {
    $this->exportController =  new \Drupal\idream_export_json\Controller\exportController();
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {

  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $entities) {
    $this->exportController->export($entities);
    //return $exportController->redirectToVisualization();
    /*foreach ($entities as $entity) {
        $this->execute($entity);
    }*/
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $key = $object->getEntityType()->getKey('published');

    /** @var \Drupal\Core\Entity\EntityInterface $object */
    $result = $object->access('update', $account, TRUE)
      ->andIf($object->$key->access('edit', $account, TRUE));

    return $return_as_object ? $result : $result->isAllowed();
  }

}