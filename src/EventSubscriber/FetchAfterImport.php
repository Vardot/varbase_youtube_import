<?php

namespace Drupal\varbase_youtube_import\EventSubscriber;

use Drupal\Component\Serialization\Json;
use Drupal\feeds\Event\FeedsEvents;
use Drupal\feeds\Event\ImportFinishedEvent;
use Drupal\feeds\StateInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Import the rest of the pages.
 */
class FetchAfterImport implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[FeedsEvents::IMPORT_FINISHED][] = 'afterImport';
    return $events;
  }

  /**
   * Called when an import has finished.
   *
   * @param \Drupal\feeds\Event\ImportFinishedEvent $event
   *   The import finished event.
   */
  public function afterImport(ImportFinishedEvent $event) {
    $feed = $event->getFeed();
    if ($feed->get('type')->target_id == 'youtube_channel' || $feed->get('type')->target_id == 'youtube_playlist') {
      $result = $feed->getType()->getFetcher()->fetch($feed, $feed->getState(StateInterface::FETCH));
      $raw = $result->getRaw();
      $parsed = Json::decode($raw);
      $old_source = $feed->get('source')->value;
      if (isset($parsed['nextPageToken'])) {
        if (strpos($old_source, 'pageToken')) {
          $pos = strpos($feed->get('source')->value, '&pageToken=');
          $new_source = substr($feed->get('source')->value, 0, $pos);
          $feed->set('source', $new_source . '&pageToken=' . $parsed['nextPageToken'], TRUE);
          $new_result = $feed->getType()->getFetcher()->fetch($feed, $feed->getState(StateInterface::FETCH));
          $new_raw = $new_result->getRaw();
          $new_parsed = Json::decode($new_raw);
          if (!isset($new_parsed['items'][0])) {
            $feed->set('source', $new_source, TRUE);
          }
        }
        else {
          $feed->set('source', $feed->get('source')->value . '&pageToken=' . $parsed['nextPageToken'], TRUE);
          $new_result = $feed->getType()->getFetcher()->fetch($feed, $feed->getState(StateInterface::FETCH));
          $new_raw = $new_result->getRaw();
          $new_parsed = Json::decode($new_raw);
          if (!isset($new_parsed['items'][0])) {
            $feed->set('source', $old_source, TRUE);
          }
        }
      }
    }
  }

}
