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

  Scenario: It tags existing value in cache
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

  Scenario: It untags existing value in cache
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
