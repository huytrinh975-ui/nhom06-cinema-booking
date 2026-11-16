
<?php
// ======================================
// 1. KHAI BÁO DANH SÁCH SẢN PHẨM
// ======================================

$products = [
    [
        "id" => 1,
        "name" => "iPhone 16",
        "brand" => "Apple",
        "price" => 22000000,
        "quantity" => 10,
        "image" => "iphone16.jpg"
    ],
    [
        "id" => 2,
        "name" => "Samsung Galaxy S25",
        "brand" => "Samsung",
        "price" => 20000000,
        "quantity" => 15,
        "image" => "samsung_s25.jpg"
    ],
    [
        "id" => 3,
        "name" => "Xiaomi 15",
        "brand" => "Xiaomi",
        "price" => 15000000,
        "quantity" => 20,
        "image" => "xiaomi15.jpg"
    ],
    [
        "id" => 4,
        "name" => "OPPO Reno 13",
        "brand" => "OPPO",
        "price" => 12000000,
        "quantity" => 8,
        "image" => "oppo_reno13.jpg"
    ]
];

// ======================================
// 2. LẤY DỮ LIỆU TÌM KIẾM VÀ LỌC
// ======================================

$search = isset($_GET["search"])
    ? trim($_GET["search"])
    : "";

$brandFilter = isset($_GET["brand"])
    ? $_GET["brand"]
    : "";

// ======================================
// 3. LỌC SẢN PHẨM
// ======================================

$filteredProducts = [];

foreach ($products as $product) {

    $matchSearch = (
        $search === "" ||
        stripos($product["name"], $search) !== false ||
        stripos($product["brand"], $search) !== false
    );

    $matchBrand = (
        $brandFilter === "" ||
        $product["brand"] === $brandFilter
    );

    if ($matchSearch && $matchBrand) {
        $filteredProducts[] = $product;
    }
}

// ======================================
// 4. LẤY DANH SÁCH THƯƠNG HIỆU
// ======================================

$brands = array_unique(
    array_column($products, "brand")
);

// ======================================
// 5. TÍNH TOÁN THỐNG KÊ
// ======================================

$totalProducts = count($products);

$totalBrands = count($brands);

$totalQuantity = 0;

foreach ($products as $product) {
    $totalQuantity += $product["quantity"];
}

// ======================================
// 6. HÀM ĐỊNH DẠNG GIÁ TIỀN
// ======================================

function formatPrice($price) {
    return number_format($price, 0, ",", ".") . " VNĐ";
}

// Hàm bảo vệ nội dung khi hiển thị HTML
function safe($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Website bán điện thoại</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
            color: #333;
        }

        /* HEADER */

        header {
            background-color: #172554;
            color: white;
            padding: 25px 8%;
        }

        header h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        header p {
            color: #dbeafe;
        }

        /* MENU */

        nav {
            background-color: #1e40af;
            padding: 15px 8%;
        }

        nav a {
            color: white;
            text-decoration: none;
            margin-right: 25px;
            font-weight: bold;
        }

        nav a:hover {
            color: #facc15;
        }

        /* CONTAINER */

        .container {
            width: 84%;
            margin: 30px auto;
        }

        /* THỐNG KÊ */

        .statistics {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background-color: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .stat-box h3 {
            color: #64748b;
            font-size: 15px;
            margin-bottom: 10px;
        }

        .stat-box p {
            color: #1d4ed8;
            font-size: 25px;
            font-weight: bold;
        }

        /* SEARCH */

        .search-section {
            background-color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .search-section h2 {
            margin-bottom: 20px;
        }

        .search-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        input, select {
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
        }

        input {
            flex: 1;
            min-width: 180px;
        }

        button {
            padding: 12px 22px;
            background-color: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
        }

        button:hover {
            background-color: #1e40af;
        }

        .reset {
            padding: 12px 20px;
            background-color: #64748b;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }

        /* PRODUCT */

        .product-title {
            margin-bottom: 20px;
        }

        .product-list {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .product-card {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: 0.3s;
        }

        .product-card:hover {
            transform: translateY(-5px);
        }

        .product-card img {
            width: 100%;
            height: 220px;
            object-fit: contain;
            padding: 15px;
            background-color: white;
        }

        .product-info {
            padding: 18px;
        }

        .product-info h3 {
            font-size: 18px;
            margin-bottom: 10px;
        }

        .brand {
            color: #64748b;
            margin-bottom: 10px;
        }

        .price {
            color: #dc2626;
            font-size: 19px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .quantity {
            color: #475569;
            margin-bottom: 15px;
        }

        .buy-button {
            display: block;
            text-align: center;
            padding: 10px;
            background-color: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }

        .buy-button:hover {
            background-color: #1e40af;
        }

        .empty {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            color: #dc2626;
        }

        /* FOOTER */

        footer {
            background-color: #172554;
            color: white;
            text-align: center;
            padding: 25px;
            margin-top: 50px;
        }

        /* RESPONSIVE */

        @media (max-width: 900px) {
            .product-list {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .container {
                width: 92%;
            }

            .statistics {
                grid-template-columns: 1fr;
            }

            .product-list {
                grid-template-columns: 1fr;
            }

            nav a {
                display: inline-block;
                margin-bottom: 10px;
            }
        }

    </style>
</head>

<body>

    <!-- HEADER -->

    <header>
        <h1>HK MOBILE STORE</h1>
        <p>Website giới thiệu và bán điện thoại trực tuyến</p>
    </header>

    <!-- MENU -->

    <nav>
        <a href="index.php">Trang chủ</a>
        <a href="#products">Sản phẩm</a>
        <a href="#contact">Liên hệ</a>
    </nav>

    <div class="container">

        <!-- THỐNG KÊ -->

        <section class="statistics">

            <div class="stat-box">
                <h3>Tổng số sản phẩm</h3>
                <p><?php echo $totalProducts; ?></p>
            </div>

            <div class="stat-box">
                <h3>Số thương hiệu</h3>
                <p><?php echo $totalBrands; ?></p>
            </div>

            <div class="stat-box">
                <h3>Tổng số lượng hàng</h3>
                <p><?php echo $totalQuantity; ?></p>
            </div>

        </section>

        <!-- TÌM KIẾM -->

        <section class="search-section">

            <h2>Tìm kiếm sản phẩm</h2>

            <form method="GET" action="index.php"
                  class="search-form">

                <input
                    type="text"
                    name="search"
                    placeholder="Nhập tên sản phẩm hoặc thương hiệu..."
                    value="<?php echo safe($search); ?>"
                >

                <select name="brand">

                    <option value="">Tất cả thương hiệu</option>

                    <?php foreach ($brands as $brand): ?>

                        <option
                            value="<?php echo safe($brand); ?>"
                            <?php
                            if ($brandFilter === $brand) {
                                echo "selected";
                            }
                            ?>
                        >
                            <?php echo safe($brand); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <button type="submit">
                    Tìm kiếm
                </button>

                <a href="index.php" class="reset">
                    Làm mới
                </a>

            </form>

        </section>

        <!-- DANH SÁCH SẢN PHẨM -->

        <section id="products">

            <h2 class="product-title">
                Danh sách sản phẩm
            </h2>

            <?php if (count($filteredProducts) > 0): ?>

                <div class="product-list">

                    <?php foreach ($filteredProducts as $product): ?>

                        <div class="product-card">

                            <img
                                src="images/<?php echo safe($product["image"]); ?>"
                                alt="<?php echo safe($product["name"]); ?>"
                                onerror="this.style.display='none';"
                            >

                            <div class="product-info">

                                <h3>
                                    <?php echo safe($product["name"]); ?>
                                </h3>

                                <p class="brand">
                                    Thương hiệu:
                                    <?php echo safe($product["brand"]); ?>
                                </p>

                                <p class="price">
                                    <?php echo formatPrice($product["price"]); ?>
                                </p>

                                <p class="quantity">
                                    Số lượng:
                                    <?php echo $product["quantity"]; ?>
                                </p>

                                <a href="#" class="buy-button">
                                    Xem sản phẩm
                                </a>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="empty">
                    Không tìm thấy sản phẩm phù hợp!
                </div>

            <?php endif; ?>

        </section>

    </div>

    <!-- FOOTER -->

    <footer id="contact">

        <h3>HK MOBILE STORE</h3>

        <p>Website thực hành lập trình PHP và HTML/CSS</p>

        <p>© 2026 HK Mobile Store</p>

    </footer>

</body>
</html>
