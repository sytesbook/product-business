# Admin CLI — Requirements Specification

## 1. Purpose and Scope

The Admin CLI is an interactive command-line tool for managing backend service resources through their REST APIs. It provides a tree-structured navigation interface analogous to a filesystem, allowing administrators to traverse and operate on API resources without manually constructing HTTP requests.

The tool is **service-agnostic**: any backend service exposing REST APIs can be targeted by supplying a configuration file, making the tool reusable across the Sytesbook backend ecosystem.

---

## 2. Key Concepts

### 2.1 Resource Types

The tool recognizes three resource types, each with distinct available commands:

**Collection Resource**
Represents a list of documents accessible at a fixed URL (e.g. `/sites`, `/sites/{uid}/domains`). Supports listing all items and creating new ones. Acts as a container for its document nodes.

**Document Resource**
Represents a single item within a collection, accessed by its identifier at runtime (e.g. `/sites/{uid}`). Supports viewing, updating, deleting, and invoking controller actions on it. Also acts as a container for nested sub-resources (sub-collections, controllers).

**Controller Resource**
Represents a custom action endpoint (e.g. `activate`, `set-as-primary`). Controllers are **not navigable nodes** — they are children of a document node and are invoked via the `invoke` command from the parent document. Controllers are leaf nodes and cannot be entered.

### 2.2 Resource Tree

The resource tree is a hierarchical structure defined in the config file. It mirrors the URL structure of the API. Collections contain document nodes as **dynamic children** (accessed by identifier at runtime), and documents may have **static children** (sub-collections or controllers, defined in the config).

### 2.3 Current Node

At any time, the user is positioned at exactly one node in the resource tree. This is the **current node**. The prompt always reflects the current node's path.

---

## 3. Configuration

### 3.1 Config File

The CLI is launched with a configuration file provided as a command-line argument. The config file defines:

- **Service name** — A human-readable label for the service being managed (displayed at startup and in the prompt).
- **Base URL** — The root URL of the API. All resource paths are resolved relative to this.
- **Resource tree** — A hierarchical definition of all accessible resource nodes.

### 3.2 Resource Node Definition

Each node in the resource tree specifies:

- **name** — used as the navigation segment (e.g. `sites`, `activate`)
- **type** — `collection` or `controller` (`document` is implicit as the dynamic child of any collection)
- **child nodes** — any child nodes defined under a document of that collection (sub-collections, controllers)
- **description** *(optional)* — shown in `help` output. Especially important for controller resources, since they represent domain-specific actions whose purpose may not be self-evident from their name alone.
- **confirmation required** *(optional, controllers only)* — whether explicit user confirmation is required before invocation.

### 3.3 Multiple Configs

The CLI may be invoked with different config files to target different services. Only one config is active per session.

### 3.4 Config Validation

If the config file is missing, malformed, or specifies invalid values (e.g. malformed base URL, duplicate node names), the CLI exits immediately with a descriptive error message before entering the interactive session.

---

## 4. Navigation

### 4.1 Session Start

When launched, the CLI displays the service name, base URL, and a brief usage hint, then positions the user at the root node and shows the prompt.

### 4.2 Prompt

The prompt always reflects the current path in the resource tree:

```
/ >
/sites >
/sites/abc-123 >
/sites/abc-123/domains >
/sites/abc-123/domains/def-456 >
```

### 4.3 Navigation Commands

| Command | Description |
|---------|-------------|
| `cd <name>` | Navigate into a named child node. For collections, `<name>` is the static name from the config. Inside a collection, `<name>` is a document identifier. |
| `cd ..` | Navigate to the parent node. Has no effect at root. |
| `cd /` | Navigate directly to the root node. |
| `ls` | List all navigable child nodes at the current position, with their types (see §4.7). |
| `pwd` | Display the full path of the current node. |

### 4.4 Navigating into a Collection

When entering a collection node (e.g. `cd sites`), the user is positioned at the collection. From there they may run collection commands (`index`, `create`) or navigate into a specific document by its identifier.

### 4.5 Navigating into a Document

Navigating into a document (e.g. `cd abc-123` from within `/sites`) causes the CLI to **validate the identifier immediately** by fetching the resource. If the resource does not exist or the request fails, an error is shown and the user stays at the current node. On success, the user is positioned at the document node.

### 4.6 Controllers Are Not Navigable

Controllers are not navigable nodes. They cannot be entered with `cd`. They are invoked via the `invoke` command from the parent document node (see §5.4).

### 4.7 `ls` at a Collection Node

When `ls` is run on a collection node that has not yet had `index` executed in the current session, the CLI displays the statically-known children (sub-collections, controllers defined in the config) and additionally shows a **hint** that document-level child nodes may exist but are not yet known — prompting the user to run `index` to discover them.

After `index` has been run, `ls` may also show the known document identifiers (or a count).

### 4.8 Root Node

The root has no commands beyond the universal ones and `ls`. Its children are the top-level resource nodes defined in the config.

---

## 5. Commands

### 5.1 Universal Commands

Available at every node:

| Command | Description |
|---------|-------------|
| `help` | Display available commands and navigable children at the current position, including any descriptions defined in the config. |
| `ls` | List navigable child nodes with their types. |
| `cd <target>` | Navigate (see §4.3). |
| `pwd` | Show the current path. |
| `clear` | Clear terminal output while preserving session state and current position. |
| `exit` / `quit` | Exit the CLI session. |

### 5.2 Collection Commands

Available when positioned at a collection node:

| Command | Description |
|---------|-------------|
| `index` | Fetch and display all documents in this collection. |
| `create` | Interactively prompt for field values and create a new document. On success, displays the created resource and its assigned identifier. |

### 5.3 Document Commands

Available when positioned at a document node:

| Command | Description |
|---------|-------------|
| `show` | Fetch and display this document. |
| `update` | Interactively prompt for new field values. Pre-populates prompts with current values where possible. On success, displays the updated resource. |
| `delete` | Delete this document after explicit confirmation. On success, navigates the user up to the parent collection. |
| `invoke <controller-name>` | Invoke a controller resource that is a child of this document (see §5.4). |

### 5.4 Invoking Controllers

Controllers are invoked from the parent document node using:

```
invoke <controller-name>
```

For example, from `/sites/abc-123`, the user types `invoke activate`. Running `help` or `ls` at a document node lists the available controllers with their names and descriptions (as defined in the config).

If a controller is configured to require **confirmation**, the CLI prompts the user before proceeding (e.g. `Are you sure? [y/N]`).

---

## 6. Input

### 6.1 Interactive Field Input

For `create` and `update`, the CLI prompts for each field individually showing:
- The field name
- Any known constraints or expected format
- For `update`: the current value as a default (pressing Enter retains it)

### 6.2 Confirmation Prompts

Destructive or irreversible operations (`delete`, and controllers marked as requiring confirmation in the config) require explicit user confirmation before proceeding.

### 6.3 Cancellation

At any input prompt, the user may cancel the in-progress operation (e.g. via `Ctrl+C`) without making any changes to resources.

---

## 7. Output

### 7.1 Output Format

The CLI supports two output modes, selectable via a flag at startup or a session-level command:

- **Human-readable (default)** — tabular and structured output optimised for visual inspection.
- **JSON** — raw API response output, suitable for piping to other tools or scripting.

The active output mode applies to all command output for the remainder of the session until changed.

### 7.2 Document Display

Single documents are displayed in a structured, human-readable format showing all fields and their values.

### 7.3 Collection Display

Collections are displayed as a list with each item showing its identifier and key fields in a compact, tabular format.

### 7.4 Success Messages

Successful mutations (create, update, delete, invoke) display a brief confirmation and the resulting resource state where applicable.

### 7.5 Error Messages

Errors are displayed in a clearly distinguishable format and include:
- The nature of the error (not found, validation failure, server error, etc.)
- Any detail message returned by the API
- The HTTP status code

The user remains at their current node after any error.

---

## 8. Error Handling

| Situation | Behavior |
|-----------|----------|
| API 4xx/5xx response | Display error details; user stays at current node and may retry. |
| `cd` into a non-existent static child | Error message; user stays at current node. |
| `cd` into a document with an invalid/non-existent identifier | Validation fails immediately; error shown; user stays at current node. |
| Network / connectivity failure | Clear error message; user stays at current node and may retry. |
| Malformed or missing config file | CLI exits before starting the session with a descriptive error. |

---

## 9. Help and Discoverability

### 9.1 In-session Help

`help` at any node displays:
- The type of the current node and its full path
- All commands available at this position with brief descriptions
- All navigable child nodes with their types
- For document nodes: all available controllers with their names and descriptions (from config)

### 9.2 Startup Banner

On launch, the CLI displays the service name, base URL, and a hint (e.g. `Type 'help' for available commands, 'ls' to see child resources.`).

---

## 10. Session Quality-of-Life

### 10.1 Command History

Within a session, previously entered commands are accessible via the up/down arrow keys.

### 10.2 Tab Completion

- **Static node names** (defined in the config) are offered as tab-completion candidates when typing `cd` or `invoke`.
- **Dynamic document identifiers** are offered as tab-completion candidates when the user is at a collection node **and** `index` has already been run in the current session (making the identifiers known). If `index` has not been run, no dynamic completions are offered.

---

## 11. Extensibility

- The tool is not tied to any specific service or resource schema; any REST API can be targeted via a config file.
- The resource type model (`collection`, `document`, `controller`) is extensible — new types or behaviors can be introduced without redesigning the navigation model.
