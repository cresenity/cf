# Application - Element

### Introduction

CApp allows you to build UI programmatically by adding elements directly. Each element maps to an HTML tag or a higher-level component. Elements are rendered in the order they are added.

### HTML Elements

Add basic HTML elements to the page:

```php
<?php
$app = c::app();
$app->addDiv();     // <div>
$app->addA();       // <a>
$app->addUl();      // <ul>
$app->addOl();      // <ol>
$app->addLi();      // <li>
$app->addSpan();    // <span>

return $app;
```

### Auto-Generated IDs

Every element gets an auto-generated unique ID if you don't provide one:

```php
$app->addDiv();
// <div id="00000000262e89430000000042533b79"></div>

$app->addDiv('my-container');
// <div id="my-container"></div>
```

### Setting Attributes and Content

Chain methods to configure elements:

```php
$div = $app->addDiv('main');
$div->setAttr('style', 'padding: 20px;');
$div->addClass('container mt-3');
$div->add('Hello, World!');
```

### Nesting Elements

Elements can be nested to create complex layouts:

```php
$container = $app->addDiv()->addClass('container');
$row = $container->addDiv()->addClass('row');

$col1 = $row->addDiv()->addClass('col-md-6');
$col1->add('<h2>Left Column</h2>');

$col2 = $row->addDiv()->addClass('col-md-6');
$col2->add('<h2>Right Column</h2>');
```

### Component Elements

CApp provides higher-level components for common UI patterns:

```php
$app->addWidget();       // Card/widget container
$app->addTable();        // Data table
$app->addForm();         // Form builder
$app->addAction();       // Action buttons group
$app->addAlert();        // Alert/notification box
$app->addTabList();      // Tabbed interface
$app->addImage();        // Image element
$app->addFileManager();  // File manager component
$app->addShowMore();     // Show more/less toggle
$app->addShimmer();      // Loading shimmer placeholder
```

### Adding Views

Embed a Blade view as an element:

```php
$app->addView('dashboard.stats', [
    'totalUsers' => 150,
    'totalOrders' => 42,
]);
```

### Data Table Example

Build a data table programmatically:

```php
$table = $app->addTable();
$table->setDataFromModel(UserModel::class);
$table->addColumn('name')->setLabel('Name');
$table->addColumn('email')->setLabel('Email');
$table->addColumn('created')->setLabel('Registered');
```

### Form Example

Build a form with fields:

```php
$form = $app->addForm();
$form->addField()->setLabel('Name')->addTextControl('name');
$form->addField()->setLabel('Email')->addEmailControl('email');
$form->addField()->setLabel('Role')->addSelectControl('role')
    ->setList([
        'admin' => 'Administrator',
        'user' => 'User',
    ]);
$form->addActionList()->addAction()->setLabel('Save')->setSubmit();
```

### Widget Example

Wrap content in a card/widget:

```php
$widget = $app->addWidget()->setTitle('Recent Activity');
$widget->addDiv()->add('Widget content here');
```
