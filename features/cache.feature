Feature: Cache service
  In order to prove that the RedisCacheService is working properly
  As a developer, a RedisCacheService user
  I need to be able to set, get, tag, delete, etc. cached values

  Scenario: It sets value in cache
    Given the redis cache instance does not contain any value under the key "example_key"
    When I add the "example_value" under the "example_key" to the cache
    Then I should have "example_value" under the "example_key" in the cache

  Scenario: It sets and tags value in cache
    Given the redis cache instance does not contain any value under the key "example_key"
    When I add the "example_value" under the "example_key" tagged with "example_tag" to the cache
    Then I should have "example_value" under the "example_key" in the cache
    And I should have "example_value" tagged by the "example_tag" under the "example_key" in the cache

  Scenario: It gets existing value from cache
    Given the redis cache instance contains "example_value" under the key "example_key"
    Then I should have "example_value" under the "example_key" in the cache

  Scenario: It tries to get non-existing value from cache
    Given the redis cache instance does not contain any value under the key "example_key"
    Then I should not have any value under the "example_key" in the cache

  Scenario: It deletes value from cache
    Given the redis cache instance contains "example_value" under the key "example_key"
    When I delete value under the "example_key" from the cache
    Then I should not have any value under the "example_key" in the cache

  Scenario: It tags existing untagged value in cache
    Given the redis cache instance contains "example_value" under the key "example_key" which is not tagged by "example_tag"
    When I tag the "example_key" with "example_tag"
    Then I should have "example_value" tagged by the "example_tag" under the "example_key" in the cache

  Scenario: It tags existing tagged value with a different tag
    Given the redis cache instance contains "example_value" under the key "example_key" which is tagged by "example_tag"
    When I tag the "example_key" with "example_tag_2"
    Then I should have "example_value" tagged by the "example_tag" under the "example_key" in the cache
    And I should have "example_value" tagged by the "example_tag_2" under the "example_key" in the cache

  Scenario: It tags non-existing key in cache
    Given the redis cache instance does not contain any value under the key "example_key"
    When I tag the "example_key" with "example_tag"
    Then I should not have key "example_key" tagged by the "example_tag" in the cache
    And I should not have any value under the "example_key" in the cache

  Scenario: It untags existing tagged value in cache
    Given the redis cache instance contains "example_value" under the key "example_key" which is tagged by "example_tag"
    When I untag the "example_key" with "example_tag"
    Then I should have "example_value" under the "example_key" in the cache
    And I should not have "example_value" tagged by the "example_tag" under the "example_key" in the cache

  Scenario: It deletes tagged key from cache
    Given the redis cache instance contains "example_value" under the key "example_key" which is tagged by "example_tag"
    When I delete value under the "example_key" from the cache
    Then I should not have any value under the "example_key" in the cache
    And I should not have key "example_key" tagged by the "example_tag" in the cache

  Scenario: It clears by tag from cache
    Given the redis cache instance is clean
    Given the redis cache instance contains "example_value" under the key "example_key" which is tagged by "example_tag"
    Given the redis cache instance contains "example_value_2" under the key "example_key_2" which is tagged by "example_tag"
    When I clear by tag "example_tag" from cache
    Then I should not have any value under the "example_key" in the cache
    And I should not have any value under the "example_key_2" in the cache
    And I should not have any key tagged by the "example_tag" in the cache

  Scenario: It clears by first tag and do not clear by second tag
    Given the redis cache instance is clean
    And the redis cache instance contains "example_value" under the key "example_key" which is tagged by "example_tag" and "example_tag_2"
    And the redis cache instance contains "example_value_2" under the key "example_key_2" which is tagged by "example_tag_2"
    When I clear by tag "example_tag" from cache
    Then I should not have any value under the "example_key" in the cache
    And I should not have key "example_key" tagged by the "example_tag" in the cache
    And I should not have key "example_key" tagged by the "example_tag_2" in the cache
    And I should have "example_value_2" under the "example_key_2" in the cache
    And I should not have key "example_key_2" tagged by the "example_tag_1" in the cache
    And I should have "example_value_2" tagged by the "example_tag_2" under the "example_key_2" in the cache
    And I should not have any key tagged by the "example_tag" in the cache
    And I should have exactly 1 key tagged by the "example_tag_2" in the cache

  Scenario: It clears by second tag and do not clear by first tag
    Given the redis cache instance is clean
    And the redis cache instance contains "example_value" under the key "example_key" which is tagged by "example_tag" and "example_tag_2"
    And the redis cache instance contains "example_value_2" under the key "example_key_2" which is tagged by "example_tag_2"
    When I clear by tag "example_tag_2" from cache
    Then I should not have any value under the "example_key" in the cache
    And I should not have any value under the "example_key_2" in the cache
    And I should not have any key tagged by the "example_tag" in the cache
    And I should not have any key tagged by the "example_tag_2" in the cache

  Scenario: It gets tagged from cache
    Given the redis cache instance is clean
    Given the redis cache instance contains "example_value" under the key "example_key" which is tagged by "example_tag"
    Given the redis cache instance contains "example_value_2" under the key "example_key_2" which is tagged by "example_tag"
    Then I should have "example_value" tagged by the "example_tag" under the "example_key" in the cache
    And I should have "example_value_2" tagged by the "example_tag" under the "example_key_2" in the cache
    And I should have exactly 2 keys tagged by the "example_tag" in the cache

  Scenario: It deletes tagged key from cache and sets new value under the same key but without tagging
    Given the redis cache instance contains "example_value" under the key "example_key" which is tagged by "example_tag"
    When I delete value under the "example_key" from the cache
    When I add the "example_value_2" under the "example_key" to the cache
    Then I should have "example_value_2" under the "example_key" in the cache
    And I should not have key "example_key" tagged by the "example_tag" in the cache

  Scenario: It sets new value under existing tagged key but without tagging
    Given the redis cache instance contains "example_value" under the key "example_key" which is tagged by "example_tag"
    When I add the "example_value_2" under the "example_key" to the cache
    Then I should have "example_value_2" under the "example_key" in the cache
    And I should not have key "example_key" tagged by the "example_tag" in the cache

  Scenario: It adds values to empty queue in cache
    Given the redis cache instance is clean
    When I add the "example_value" to the queue "example_queue"
    When I add the "example_value_2" to the queue "example_queue"
    When I add the "example_value_3" to the queue "example_queue"
    Then I should have the queue "example_queue" containing items in the following order:
      | example_value   |
      | example_value_2 |
      | example_value_3 |

  Scenario: It adds non-unique values to empty queue in cache to prove that it is not a set
    Given the redis cache instance is clean
    When I add the "example_value" to the queue "example_queue"
    When I add the "example_value" to the queue "example_queue"
    When I add the "example_value_2" to the queue "example_queue"
    When I add the "example_value" to the queue "example_queue"
    Then I should have the queue "example_queue" containing items in the following order:
      | example_value   |
      | example_value   |
      | example_value_2 |
      | example_value   |

  Scenario: It tries to add null item to non-empty queue in cache
    Given the redis cache instance is clean
    When I add the "example_value" to the queue "example_queue"
    When I add the "example_value_2" to the queue "example_queue"
    When I try to add null item to the queue "example_queue"
    Then I should have the queue "example_queue" containing items in the following order:
      | example_value   |
      | example_value_2 |

  Scenario: It tries to add null item then non-null value to non-empty queue in cache
    Given the redis cache instance is clean
    When I add the "example_value" to the queue "example_queue"
    When I add the "example_value_2" to the queue "example_queue"
    When I try to add null item to the queue "example_queue"
    When I add the "example_value_3" to the queue "example_queue"
    Then I should have the queue "example_queue" containing items in the following order:
      | example_value   |
      | example_value_2 |
      | example_value_3 |

  Scenario: It pops only first item from the queue
    Given the redis cache instance is clean
    When I add the "example_value" to the queue "example_queue"
    When I add the "example_value_2" to the queue "example_queue"
    When I add the "example_value_3" to the queue "example_queue"
    When I pop item from the queue "example_queue"
    Then I should have popped items in the following order:
      | example_value |
    And I should have the queue "example_queue" containing items in the following order:
      | example_value_2 |
      | example_value_3 |

  Scenario: It pops item from empty queue in cache
    Given the redis cache instance is clean
    When I pop item from the queue "example_queue"
    Then I should have popped null item
    And I should have empty queue "example_queue"

  Scenario: It pops all items from the queue
    Given the redis cache instance is clean
    When I add the "example_value" to the queue "example_queue"
    When I add the "example_value_2" to the queue "example_queue"
    When I pop item from the queue "example_queue"
    When I pop item from the queue "example_queue"
    Then I should have popped items in the following order:
      | example_value   |
      | example_value_2 |
    And I should have empty queue "example_queue"

  Scenario: It pops item adds it to the queue
    Given the redis cache instance is clean
    When I add the "example_value" to the queue "example_queue"
    When I add the "example_value_2" to the queue "example_queue"
    When I add the "example_value_3" to the queue "example_queue"
    When I pop item from the queue "example_queue"
    When I add the "example_value" to the queue "example_queue"
    Then I should have popped items in the following order:
      | example_value |
    And I should have the queue "example_queue" containing items in the following order:
      | example_value_2 |
      | example_value_3 |
      | example_value   |

  Scenario: It clears non-empty queue in cache
    Given the redis cache instance is clean
    When I add the "example_value" to the queue "example_queue"
    When I add the "example_value_2" to the queue "example_queue"
    When I add the "example_value_3" to the queue "example_queue"
    When I pop everything from the queue "example_queue"
    Then I should have popped items in the following order:
      | example_value   |
      | example_value_2 |
      | example_value_3 |
    And I should have empty queue "example_queue"

  Scenario: It retrieves items from the queue without dequeuing them (pop range)
    Given the redis cache instance is clean
    When I add the "example_value" to the queue "example_queue"
    When I add the "example_value_2" to the queue "example_queue"
    When I add the "example_value_3" to the queue "example_queue"
    When I add the "example_value_4" to the queue "example_queue"
    When I add the "example_value_5" to the queue "example_queue"
    When I pop range "2" from the queue "example_queue"
    Then I should have retrieved items in the following order:
      | example_value   |
      | example_value_2 |
      | example_value_3 |
    And I should have the queue "example_queue" containing items in the following order:
      | example_value   |
      | example_value_2 |
      | example_value_3 |
      | example_value_4 |
      | example_value_5 |

  Scenario: It retrieves items from the queue without dequeuing them (pop with negative range)
    Given the redis cache instance is clean
    When I add the "example_value" to the queue "example_queue"
    When I add the "example_value_2" to the queue "example_queue"
    When I add the "example_value_3" to the queue "example_queue"
    When I add the "example_value_4" to the queue "example_queue"
    When I add the "example_value_5" to the queue "example_queue"
    When I pop range "-2" from the queue "example_queue"
    Then I should have retrieved items in the following order:
      | example_value   |
      | example_value_2 |
      | example_value_3 |
      | example_value_4 |
    And I should have the queue "example_queue" containing items in the following order:
      | example_value   |
      | example_value_2 |
      | example_value_3 |
      | example_value_4 |
      | example_value_5 |

