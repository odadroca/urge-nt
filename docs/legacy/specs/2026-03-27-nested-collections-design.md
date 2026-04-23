# Nested Collections

## Problem

Collections are flat — they can only contain prompt versions and results. Users need to compose narratives from smaller building blocks: a "Full Demo" collection that includes "Setup", "Results", and "Analysis" sub-collections as chapters. This is the foundation for a future graph/mind-map visualization.

## Design Principle

The collection tree is a curated narrative view. It does NOT constrain artifact version lineage. A prompt version can appear in many collections simultaneously — branching (version axis) and narrative (collection axis) are independent.

## Approach

Add `collection` as a third polymorphic type in `CollectionItem` (alongside `prompt_version` and `result`). A `CollectionItem` with `item_type=collection, item_id=7` means "collection #7 is nested here." No new tables needed.

Since a collection can appear in multiple parents, this is a directed acyclic graph (DAG). Circular reference detection prevents cycles. Depth is configurable (default 5, with an unlimited toggle).

## Data Model Changes

- **Morph map**: add `'collection' => Collection::class` to `AppServiceProvider`
- **Config**: add `max_collection_depth` (default 5) and `unlimited_collection_depth` (default false) to `config/urge.php`

## Nesting Validation (CollectionNestingService)

Before inserting a collection-type item:
1. Self-reference check: parent cannot equal child
2. Circular reference: walk ancestor chain upward — reject if child appears in ancestors
3. Depth check: total depth (ancestors above + descendants below) must not exceed configured max

Circular reference detection always runs, even when depth is unlimited.

## UI Behavior

- **Browse/CollectionList**: "Add to collection" action on collection cards, nested collections render as collapsible group cards in expanded view
- **Public story view**: Nested collections render as chapter sections with title/description heading and grouped child items. Beyond max depth, show summary card instead of expanding.

## API

No new endpoints. Existing `POST /collections/{slug}/items` accepts `item_type=collection`. Existing `GET /collections/{slug}` returns recursive structure with depth cap.
