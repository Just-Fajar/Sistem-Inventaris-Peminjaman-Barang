<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="Sistem Inventaris & Peminjaman Barang API",
 *     version="1.0.0",
 *     description="API Documentation untuk Sistem Inventaris & Peminjaman Barang",
 *     @OA\Contact(
 *         email="admin@inventaris.com"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="Development Server"
 * )
 * 
 * @OA\Server(
 *     url="https://api.inventaris.com/api",
 *     description="Production Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter your Bearer token in the format: Bearer {token}"
 * )
 */
abstract class Controller
{
    //
}
