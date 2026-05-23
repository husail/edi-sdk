# husail/edi-sdk

Generic PHP SDK for reading, writing and validating fixed-width EDI files.

![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4)
![License](https://img.shields.io/badge/license-MIT-green)
![Status](https://img.shields.io/badge/status-active-success)

---

## 📋 Requirements

- PHP 8.2+
- `symfony/yaml` (for YAML layout driver)

---

## 📦 Installation

```bash
composer require husail/edi-sdk
```

---

## 🧠 Core concepts

Define the file layout once and reuse it for:

- writing files
- parsing files
- validating files

The SDK handles:

- field positions and lengths
- padding and normalization
- numeric formatting
- line endings
- typed value casting
- structural validation

Layouts can be defined using:

- PHP
- YAML
- JSON

It also supports complex file structures such as:

- headers and trailers
- repeatable groups
- interleaved record types
- CNAB-style batch structures

---

## 📐 Defining a layout

### PHP Builder

```php
use Husail\EdiSdk\Schema\FieldType;
use Husail\EdiSdk\Schema\FileLayout;
use Husail\EdiSdk\Schema\RecordLayout;
use Husail\EdiSdk\Schema\Sequence\Record;
use Husail\EdiSdk\Schema\Sequence\Group;

$header = RecordLayout::define('header')
    ->lineLength(50)
    ->addField(name: 'type',    pos: 1,  len: 1,  type: FieldType::ALPHA,   const: 'H')
    ->addField(name: 'company', pos: 2,  len: 20, type: FieldType::ALPHA)
    ->addField(name: 'date',    pos: 22, len: 8,  type: FieldType::NUMERIC)
    ->addField(name: 'filler',  pos: 30, len: 21, type: FieldType::ALPHA,   required: false)
    ->build();

$detail = RecordLayout::define('detail')
    ->lineLength(50)
    ->addField(name: 'type',     pos: 1,  len: 1,  type: FieldType::ALPHA,   const: 'D')
    ->addField(name: 'invoice',  pos: 2,  len: 10, type: FieldType::NUMERIC)
    ->addField(name: 'customer', pos: 12, len: 20, type: FieldType::ALPHA)
    ->addField(name: 'amount',   pos: 32, len: 15, type: FieldType::NUMERIC, cast: 'float', decimalPlaces: 2)
    ->build();

$trailer = RecordLayout::define('trailer')
    ->lineLength(50)
    ->addField(name: 'type',          pos: 1, len: 1,  type: FieldType::ALPHA,   const: 'T')
    ->addField(name: 'total_records', pos: 2, len: 6,  type: FieldType::NUMERIC, cast: 'int')
    ->addField(name: 'total_amount',  pos: 8, len: 15, type: FieldType::NUMERIC, cast: 'float', decimalPlaces: 2)
    ->build();

$layout = FileLayout::define('my-edi')
    ->lineLength(50)
    ->lineEnding("\r\n")
    ->addRecord($header)
    ->addRecord($detail)
    ->addRecord($trailer)
    ->withSequence([
        Record::one($header),
        Record::many($detail),
        Record::one($trailer),
    ])
    ->build();
```

### YAML

```yaml
name: my-edi
line_length: 50
line_ending: '\r\n'

records:
  - name: header
    fields:
      - { name: type,    pos: 1,  len: 1,  type: alpha,   const: H }
      - { name: company, pos: 2,  len: 20, type: alpha }
      - { name: date,    pos: 22, len: 8,  type: numeric }
      - { name: filler,  pos: 30, len: 21, type: alpha,   required: false }

  - name: detail
    fields:
      - { name: type,     pos: 1,  len: 1,  type: alpha,   const: D }
      - { name: invoice,  pos: 2,  len: 10, type: numeric }
      - { name: customer, pos: 12, len: 20, type: alpha }
      - { name: amount,   pos: 32, len: 15, type: numeric, decimal_places: 2, cast: float }

  - name: trailer
    fields:
      - { name: type,          pos: 1, len: 1,  type: alpha,   const: T }
      - { name: total_records, pos: 2, len: 6,  type: numeric, cast: int }
      - { name: total_amount,  pos: 8, len: 15, type: numeric, decimal_places: 2, cast: float }

sequence:
  - { type: record, record: header }
  - { type: many,   record: detail }
  - { type: record, record: trailer }
```

```php
use Husail\EdiSdk\Drivers\YamlDriver;

$layout = (new YamlDriver())->load('/path/to/my-edi.yaml');
```

> **`line_ending`** must use single-quoted escape sequences (`'\r\n'`, `'\n'`).
> Double quotes cause YAML to interpret the escape before the SDK processes it.

### JSON

Same structure as YAML.

```php
use Husail\EdiSdk\Drivers\JsonDriver;

$layout = (new JsonDriver())->load('/path/to/my-edi.json');
```

---

## 📝 Writing a file

```php
use Husail\EdiSdk\Edi;

// Write to string
$content = Edi::write($layout)
    ->add('header', [
        'company' => 'ACME LTDA',
        'date'    => '06052026',
    ])
    ->add('detail', [
        'invoice'  => 1001,
        'customer' => 'JOAO SILVA',
        'amount'   => 150.75,
    ])
    ->add('detail', [
        'invoice'  => 1002,
        'customer' => 'MARIA SOUZA',
        'amount'   => 89.90,
    ])
    ->add('trailer', [
        'total_records' => 4,
        'total_amount'  => 240.65,
    ])
    ->toString();

// Save to file
Edi::write($layout)->add(...)->toFile('/path/to/file.txt');
```

---

## 📂 Reading a file

```php
use Husail\EdiSdk\Edi;

$result = Edi::parse(file_get_contents('/path/to/file.txt'), $layout);

// Access a single record
$header = $result->first('header');
echo $header?->get('company'); // 'ACME LTDA          '
echo $header?->get('nonexistent', default: 'fallback'); // 'fallback'

// Access a collection of records
$details = $result->records('detail');
$details->count();
$details->first()?->get('amount');  // 150.75 (float, when cast: float is set)
$details->last()?->get('customer');
$details->nth(1)?->get('invoice');

// Filter
$highValue = $details->filter(fn ($r) => $r->get('amount') > 100);
$highValue->count(); // 1

// Iterate
$details->each(fn ($r) => process($r));

// Retrocompatibility — returns array of arrays
$details->toArray();
$result->toArray();
```

> Without `cast` defined on the field, the parser returns raw strings.
> Add `cast: int`, `cast: float` or `cast: date` to the field definition for automatic conversion.

---

## ✅ Validating a file

```php
use Husail\EdiSdk\Edi;

$result = Edi::validate(file_get_contents('/path/to/file.txt'), $layout);

if ($result->passes()) {
    // file is valid
}

foreach ($result->errors() as $error) {
    echo "Line {$error->line} [{$error->record}] {$error->field}: {$error->message}";
}

$result->errorsForLine(3);
$result->errorsForRecord('detail');
$result->errorCount();
```

---

## 🌳 Sequence nodes

The sequence tree describes how records are ordered and grouped in the file.

| Node | Factory | Description |
|---|---|---|
| `RecordNode` | `Record::one($layout)` | Exactly one required record |
| `RecordNode` | `Record::optional($layout)` | One optional record |
| `ManyNode` | `Record::many($layout)` | Zero or more records of the same type |
| `GroupNode` | `Group::repeat($identifyBy, $children)` | Repeatable group of records (e.g. batches) |
| `AmbiguousNode` | `Group::ambiguous($identifyBy, $children)` | Interleaved record types at the same position |

The `identifyBy` closure receives the raw line and returns the record name it belongs to, or `null` to close the group.

### Example: repeatable batches

```php
use Husail\EdiSdk\Schema\Sequence\Record;
use Husail\EdiSdk\Schema\Sequence\Group;

$layout = FileLayout::define('cnab-like')
    ->lineLength(240)
    ->addRecord($fileHeader)
    ->addRecord($batchHeader)
    ->addRecord($segmentA)
    ->addRecord($segmentB)
    ->addRecord($batchTrailer)
    ->addRecord($fileTrailer)
    ->withSequence([
        Record::one($fileHeader),

        Group::repeat(
            identifyBy: fn (string $line): ?string => match ($line[7]) {
                '1' => 'batch_header',
                '3' => 'detail',
                '5' => 'batch_trailer',
                default => null,
            },
            children: [
                Record::one($batchHeader),

                Group::ambiguous(
                    identifyBy: fn (string $line): ?string => match ($line[13]) {
                        'A' => 'segment_a',
                        'B' => 'segment_b',
                        default => null,
                    },
                    children: [
                        Record::many($segmentA),
                        Record::optional($segmentB),
                    ]
                ),

                Record::one($batchTrailer),
            ]
        ),

        Record::one($fileTrailer),
    ])
    ->build();
```

### Composite identify_by in YAML

Some formats use the same character at a given position for multiple record types.
The YAML driver supports composite `identify_by` rules with multiple match conditions.
More specific rules must come first — the first matching rule wins.

```yaml
- type: ambiguous
  identify_by:
    # segment_b_pix shares 'B' at pos 14 with segment_b
    - record: segment_b_pix
      match:
        - { pos: 14, len: 1, value: "B" }
        - { pos: 15, len: 2, in: ["01", "02", "03", "04"] }

    - record: segment_b
      match:
        - { pos: 14, len: 1, value: "B" }

    # segment_j52 shares 'J' at pos 14 with segment_j
    - record: segment_j52
      match:
        - { pos: 14, len: 1, value: "J" }
        - { pos: 18, len: 2, value: "52" }

    - record: segment_j
      match:
        - { pos: 14, len: 1, value: "J" }
```

Each match supports `value` (exact equality) and `in` (list of accepted values).
`children` is optional when `identify_by` is present — the driver automatically infers a `ManyNode`
for each record declared in the rules, preserving order. If neither `children` nor `identify_by`
is present, a `LayoutException` is thrown.

---

## 📑 Field definition reference

| Property | Type | Description |
|---|---|---|
| `name` | `string` | Field key in parser output |
| `pos` | `int` | Start position, 1-based |
| `len` | `int` | Length in characters |
| `type` | `alpha\|numeric` | Determines default padding |
| `const` | `?string` | Fixed value — writer ignores input, validator enforces |
| `default` | `?string` | Fallback when value is null or empty |
| `required` | `bool` | Validator emits error when ALPHA field is empty (default: `true`) |
| `cast` | `?string` | Parser cast: `int`, `float`, `date` |
| `decimal_places` | `int` | Implicit decimal places for numeric values (requires `cast: float`) |
| `format` | `?string` | Date format, required when `cast: date` (e.g. `dmY`) |
| `padding_char` | `?string` | Overrides default padding char for the type |
| `padding_side` | `left\|right` | Overrides default padding side for the type |

### Default padding

| Type | Char | Side |
|---|---|---|
| `alpha` | space | right |
| `numeric` | `0` | left |

> `required` only applies to ALPHA fields. For NUMERIC, zeros are valid values and
> cannot be distinguished from unfilled fields in a fixed-width format.

---

## ✔️ Custom validators

```php
$record = RecordLayout::define('detail')
    ->lineLength(50)
    ->addField(...)
    ->addValidator(function (array $data): ?string {
        if ($data['amount'] === '000000000000000') {
            return 'amount cannot be zero';
        }

        return null;
    })
    ->build();
```

---

## ⚙️ Custom layout driver

Implement `LayoutDriverInterface` to load layouts from any source.
Your driver is responsible only for parsing the format — the `ArrayLayoutMapper`
handles building the `FileLayout` from the normalized array.

```php
use Husail\EdiSdk\Contracts\LayoutDriverInterface;
use Husail\EdiSdk\Schema\FileLayout;
use Husail\EdiSdk\Schema\Mapping\ArrayLayoutMapper;

class XmlLayoutDriver implements LayoutDriverInterface
{
    private ArrayLayoutMapper $mapper;

    public function __construct()
    {
        $this->mapper = new ArrayLayoutMapper();
    }

    public function load(mixed $source): FileLayout
    {
        $data = $this->parseXml($source); // convert XML → normalized array
        return $this->mapper->map($data);
    }
}
```

---

## 🧪 Testing

```bash
composer install
composer test
```

---

## 🤝 Contributing

Contributions, issues and pull requests are welcome. \
If you find a bug or have a suggestion, feel free to open an issue.

---

## 📜 License

Licensed under the [MIT License](LICENSE.md).
