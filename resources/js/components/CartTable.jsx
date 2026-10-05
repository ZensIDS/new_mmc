import React, { useEffect, useImperativeHandle, useRef, useState } from "react";
import { formatIdNumber, formatRupiah, parseIdNumber } from "../utils";
import CartTableBody from "./CartTableBody";
import ReactSelectField from "./ReactSelectField";
import Vouchers from "./Vouchers";

const modalStyle = {
    position: "fixed",
    inset: 0,
    background: "rgba(0, 0, 0, .45)",
    zIndex: 1050,
    padding: "10vh 15px",
};

const panelStyle = {
    background: "#fff",
    maxWidth: 520,
    margin: "0 auto",
    borderRadius: 4,
    boxShadow: "0 8px 30px rgba(0,0,0,.3)",
};

const CartTable = ({
    cart,
    getBaseSubtotal,
    getSubtotal,
    promotionTotal,
    voucherBreakdown,
    voucherTotal,
    grandTotal,
    customers,
    customerId,
    setCustomerId,
    onCreateCustomer,
    customerInputRef,
    paidAmount,
    setPaidAmount,
    paymentMethods,
    paymentMethodId,
    setPaymentMethodId,
    paymentReference,
    setPaymentReference,
    paymentMethodInputRef,
    paymentReferenceInputRef,
    paidInputRef,
    voucherInputRef,
    outletId,
    appliedVouchers,
    onAddVoucher,
    onRemoveVoucher,
    appliedPromotions,
    onAddPromotion,
    onRemovePromotion,
    selectedProducts,
    appliedPromotionNames,
    promotionTableRef,
    onSyncPromotions,
    handleChangeQty,
    handleClickIncrease,
    handleClickDecrease,
    handleClickDelete,
    handleEmptyCart,
    handleSubmit,
    isSubmitting,
    errorMessage,
    selectedCartProductId,
    setSelectedCartProductId,
    cartTableRef,
    onFocusBarcode,
}) => {
    const change = Math.max(0, parseIdNumber(paidAmount) - grandTotal);
    const selectedPaymentMethod = paymentMethods.find((method) => String(method.id) === String(paymentMethodId));
    const requiresPaymentReference = Boolean(paymentMethodId) && !/tunai|cash/i.test(selectedPaymentMethod?.name || "");

    const selectedCustomer = customers.find((customer) => String(customer.id) === String(customerId));
    const customerLabel = selectedCustomer?.name || "Umum";
    const paymentLabel = selectedPaymentMethod?.name || "Tunai";

    // Customer (F4) & Metode Pembayaran (F7) hanya dibuka lewat shortcut keyboard.
    const [customerModalOpen, setCustomerModalOpen] = useState(false);
    const [newCustomerMode, setNewCustomerMode] = useState(false);
    const [newCustomer, setNewCustomer] = useState({ name: "", no_telp: "", alamat: "" });
    const [newCustomerError, setNewCustomerError] = useState("");
    const [newCustomerSaving, setNewCustomerSaving] = useState(false);
    const newCustomerNameRef = useRef(null);
    const [paymentModalOpen, setPaymentModalOpen] = useState(false);
    const referenceInputRef = useRef(null);

    const resetNewCustomer = () => {
        setNewCustomerMode(false);
        setNewCustomer({ name: "", no_telp: "", alamat: "" });
        setNewCustomerError("");
        setNewCustomerSaving(false);
    };
    const closeCustomerModal = () => {
        setCustomerModalOpen(false);
        resetNewCustomer();
        onFocusBarcode?.();
    };
    const openNewCustomer = () => {
        setNewCustomerError("");
        setNewCustomerMode(true);
        window.setTimeout(() => newCustomerNameRef.current?.focus(), 50);
    };
    const submitNewCustomer = async (event) => {
        event.preventDefault();
        if (newCustomerSaving) return;
        if (!newCustomer.name.trim()) {
            setNewCustomerError("Nama wajib diisi.");
            return;
        }
        setNewCustomerSaving(true);
        setNewCustomerError("");
        try {
            await onCreateCustomer({
                name: newCustomer.name.trim(),
                no_telp: newCustomer.no_telp.trim(),
                alamat: newCustomer.alamat.trim(),
            });
            closeCustomerModal();
        } catch (error) {
            const errors = error.response?.data?.errors;
            const first = errors ? Object.values(errors)[0]?.[0] : null;
            setNewCustomerError(first || error.response?.data?.message || "Gagal menyimpan customer.");
            setNewCustomerSaving(false);
        }
    };
    const closePaymentModal = () => {
        setPaymentModalOpen(false);
        onFocusBarcode?.();
    };

    useImperativeHandle(customerInputRef, () => ({
        focus: () => {
            setPaymentModalOpen(false);
            setNewCustomerMode(false);
            setCustomerModalOpen(true);
        },
    }), []);

    useImperativeHandle(paymentMethodInputRef, () => ({
        focus: () => {
            setCustomerModalOpen(false);
            setPaymentModalOpen(true);
        },
    }), []);

    // Dipakai saat Process ditekan tapi Nomor Referensi masih kosong: buka popup pembayaran.
    useImperativeHandle(paymentReferenceInputRef, () => ({
        focus: () => {
            setCustomerModalOpen(false);
            setPaymentModalOpen(true);
            window.setTimeout(() => referenceInputRef.current?.focus(), 80);
        },
    }), []);

    useEffect(() => {
        const closeOnEscape = (event) => {
            if (event.key !== "Escape") return;
            setCustomerModalOpen(false);
            setNewCustomerMode(false);
            setPaymentModalOpen(false);
        };
        window.addEventListener("keydown", closeOnEscape);

        return () => window.removeEventListener("keydown", closeOnEscape);
    }, []);

    return (
        <>
            <div className="table-responsive text-nowrap" style={{ minHeight: "40vh", maxHeight: "calc(100vh - 420px)", overflowY: "auto", border: "1px solid #ddd" }}>
                <table className="table table-sm table-bordered">
                    <thead style={{ position: "sticky", top: 0, zIndex: 1, background: "#fff" }}>
                        <tr>
                            <th className="w-40">Produk</th>
                            <th className="w-10">Qty</th>
                            <th className="w-15">Harga Netto</th>
                            <th className="w-15">Aksi</th>
                            <th className="text-right w-20">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        {!cart.length && <tr><td colSpan="5" className="text-center text-muted" style={{ padding: "28px 8px" }}>Belum ada item. Scan barcode atau pilih produk.</td></tr>}
                        <CartTableBody
                            ref={cartTableRef}
                            cart={cart}
                            handleChangeQty={handleChangeQty}
                            handleClickIncrease={handleClickIncrease}
                            handleClickDecrease={handleClickDecrease}
                            handleClickDelete={handleClickDelete}
                            selectedCartProductId={selectedCartProductId}
                            setSelectedCartProductId={setSelectedCartProductId}
                        />
                    </tbody>
                </table>
            </div>
            <div
                style={{
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    gap: 12,
                    padding: "6px 10px",
                    border: "1px solid #ddd",
                    borderTop: 0,
                    background: "#f9f9f9",
                }}
            >
                <div className="small text-muted" style={{ display: "flex", flexWrap: "wrap", columnGap: 14, rowGap: 0, lineHeight: 1.5 }}>
                    <span>Subtotal: <b>{formatRupiah(getBaseSubtotal(cart))}</b></span>
                    {promotionTotal > 0 && <span>Promo: <b className="text-danger">-{formatRupiah(promotionTotal)}</b></span>}
                    {voucherBreakdown.map((voucher) => (
                        <span key={voucher.code}>Voucher {voucher.code}: <b className="text-danger">-{formatRupiah(voucher.amount)}</b></span>
                    ))}
                    {voucherTotal > 0 && voucherBreakdown.length > 1 && <span>Total Voucher: <b className="text-danger">-{formatRupiah(voucherTotal)}</b></span>}
                </div>
                <div style={{ whiteSpace: "nowrap", fontSize: 20, fontWeight: 700 }}>
                    <span style={{ fontSize: 13, fontWeight: 600, marginRight: 8 }}>Grand Total</span>
                    {formatRupiah(grandTotal)}
                </div>
            </div>
            <Vouchers
                appliedVouchers={appliedVouchers}
                onAddVoucher={onAddVoucher}
                onRemoveVoucher={onRemoveVoucher}
                inputRef={voucherInputRef}
                outletId={outletId}
                appliedPromotionNames={appliedPromotionNames}
                appliedPromotions={appliedPromotions}
                onAddPromotion={onAddPromotion}
                onRemovePromotion={onRemovePromotion}
                selectedProducts={selectedProducts}
                promotionTableRef={promotionTableRef}
                voucherBreakdown={voucherBreakdown}
                onSyncPromotions={onSyncPromotions}
            />
            <div className="row">
                <div className="col-md-12">
                    <p className="text-muted" style={{ margin: "8px 0 0" }}>
                        Customer: <b>{customerLabel}</b> &nbsp;|&nbsp; Pembayaran: <b>{paymentLabel}</b>
                        {requiresPaymentReference && paymentReference.trim() ? <> (Ref: {paymentReference.trim()})</> : null}
                    </p>
                </div>
                <div className="col-md-6">
                    <label>Uang Diterima <span className="text-danger">*</span> <small>(F9)</small></label>
                    <div className="input-group input-group-sm">
                        <input
                            ref={paidInputRef}
                            type="text"
                            inputMode="numeric"
                            autoComplete="off"
                            className="form-control"
                            style={{ height: "40px", fontSize: "16px" }}
                            placeholder={formatIdNumber(grandTotal)}
                            value={paidAmount}
                            onChange={(event) => {
                                const value = event.target.value;
                                setPaidAmount(value === "" ? "" : formatIdNumber(value));
                            }}
                        />
                    </div>
                    <small className="text-muted">Masukkan contoh: 100.000. Nilai disimpan sebagai 100000.</small>
                </div>
                <div className="col-md-6">
                    <label>Kembalian</label>
                    <div style={{ minHeight: 40, padding: "7px 12px", border: "1px solid #00a65a", borderRadius: 4, background: "#f0fff4", color: "#008d4c", fontSize: 22, fontWeight: 700, textAlign: "right" }}>
                        {formatRupiah(change)}
                    </div>
                </div>
            </div>
            <p className="text-muted small" style={{ marginTop: 10, marginBottom: 0 }}>
                <i className="fa fa-keyboard-o"></i> F2 Cari produk &nbsp;|&nbsp; F3 Scan &nbsp;|&nbsp; F4 Customer &nbsp;|&nbsp; F5 Item &nbsp;|&nbsp; F6 Promo &nbsp;|&nbsp; F7 Pembayaran &nbsp;|&nbsp; F8 Voucher &nbsp;|&nbsp; F9 Uang &nbsp;|&nbsp; F10 Proses &nbsp;|&nbsp; Delete hapus baris terpilih
            </p>
            {errorMessage && <div className="alert alert-danger" style={{ marginTop: 8 }}>{errorMessage}</div>}
            <div className="row" style={{ marginTop: 10 }}>
                <div className="col-sm-6">
                    <button type="button" className="btn btn-danger btn-block" onClick={handleEmptyCart} disabled={!cart.length}>Kosongkan</button>
                </div>
                <div className="col-sm-6">
                <button type="button" className="btn btn-success btn-block" onClick={handleSubmit} disabled={isSubmitting || !cart.length}>
                    {isSubmitting ? "Processing..." : "Process (F10)"}
                </button>
                </div>
            </div>
            {customerModalOpen && (
                <div style={modalStyle} role="dialog" aria-modal="true" onClick={closeCustomerModal}>
                    <div style={panelStyle} onClick={(event) => event.stopPropagation()}>
                        <div className="box-header with-border">
                            <button type="button" className="close" onClick={closeCustomerModal} aria-label="Tutup">&times;</button>
                            <h3 className="box-title"><i className="fa fa-user"></i> Customer <small>(F4)</small></h3>
                        </div>
                        <div className="box-body">
                            {!newCustomerMode ? (
                                <>
                                    <button type="button" className="btn btn-default btn-block" style={{ marginBottom: 10 }} onClick={openNewCustomer}>
                                        <i className="fa fa-plus"></i> Tambah Customer Baru
                                    </button>
                                    <ReactSelectField
                                        value={customerId}
                                        onChange={(value) => {
                                            setCustomerId(value);
                                            closeCustomerModal();
                                        }}
                                        placeholder="Pilih customer"
                                        isClearable={false}
                                        autoFocus
                                        defaultMenuIsOpen
                                        options={[
                                            { value: "", label: "Umum" },
                                            ...customers.map((customer) => ({
                                                value: customer.id,
                                                label: `${customer.name}${customer.no_telp ? ` — ${customer.no_telp}` : ""}`,
                                            })),
                                        ]}
                                    />
                                </>
                            ) : (
                                <form onSubmit={submitNewCustomer}>
                                    <div className="form-group">
                                        <label>Nama <span className="text-danger">*</span></label>
                                        <input
                                            ref={newCustomerNameRef}
                                            type="text"
                                            className="form-control"
                                            placeholder="Nama customer"
                                            value={newCustomer.name}
                                            onChange={(event) => setNewCustomer({ ...newCustomer, name: event.target.value })}
                                        />
                                    </div>
                                    <div className="form-group">
                                        <label>No. Telp <small className="text-muted">(opsional)</small></label>
                                        <input
                                            type="text"
                                            inputMode="tel"
                                            className="form-control"
                                            placeholder="08xxxxxxxxxx"
                                            value={newCustomer.no_telp}
                                            onChange={(event) => setNewCustomer({ ...newCustomer, no_telp: event.target.value })}
                                        />
                                    </div>
                                    <div className="form-group">
                                        <label>Alamat <small className="text-muted">(opsional)</small></label>
                                        <input
                                            type="text"
                                            className="form-control"
                                            placeholder="Alamat customer"
                                            value={newCustomer.alamat}
                                            onChange={(event) => setNewCustomer({ ...newCustomer, alamat: event.target.value })}
                                        />
                                    </div>
                                    {newCustomerError && <div className="alert alert-danger" style={{ padding: "6px 10px" }}>{newCustomerError}</div>}
                                    <div className="row">
                                        <div className="col-xs-5">
                                            <button type="button" className="btn btn-default btn-block" onClick={resetNewCustomer} disabled={newCustomerSaving}>Kembali</button>
                                        </div>
                                        <div className="col-xs-7">
                                            <button type="submit" className="btn btn-primary btn-block" disabled={newCustomerSaving}>
                                                {newCustomerSaving ? "Menyimpan..." : "Simpan & Pilih"}
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            )}
                        </div>
                    </div>
                </div>
            )}
            {paymentModalOpen && (
                <div style={modalStyle} role="dialog" aria-modal="true" onClick={closePaymentModal}>
                    <div style={panelStyle} onClick={(event) => event.stopPropagation()}>
                        <div className="box-header with-border">
                            <button type="button" className="close" onClick={closePaymentModal} aria-label="Tutup">&times;</button>
                            <h3 className="box-title"><i className="fa fa-credit-card"></i> Metode Pembayaran <small>(F7)</small></h3>
                        </div>
                        <div className="box-body">
                            <ReactSelectField
                                value={paymentMethodId}
                                onChange={(value) => {
                                    setPaymentMethodId(value);
                                    const method = paymentMethods.find((item) => String(item.id) === String(value));
                                    const needsReference = Boolean(value) && !/tunai|cash/i.test(method?.name || "");
                                    if (needsReference) {
                                        window.setTimeout(() => referenceInputRef.current?.focus(), 80);
                                    } else {
                                        closePaymentModal();
                                    }
                                }}
                                placeholder="Pilih metode pembayaran"
                                isClearable={false}
                                autoFocus
                                defaultMenuIsOpen
                                options={[
                                    { value: "", label: "Tunai / belum dipilih" },
                                    ...paymentMethods.map((method) => ({ value: method.id, label: method.name })),
                                ]}
                            />
                            {requiresPaymentReference && (
                                <div className="form-group" style={{ marginTop: 10 }}>
                                    <label>Nomor Referensi <span className="text-danger">*</span></label>
                                    <input
                                        ref={referenceInputRef}
                                        type="text"
                                        className="form-control"
                                        placeholder="Nomor transaksi / referensi pembayaran"
                                        value={paymentReference}
                                        required
                                        aria-required="true"
                                        onChange={(event) => setPaymentReference(event.target.value)}
                                        onKeyDown={(event) => {
                                            if (event.key === "Enter") {
                                                event.preventDefault();
                                                if (paymentReference.trim()) closePaymentModal();
                                            }
                                        }}
                                    />
                                </div>
                            )}
                            <button type="button" className="btn btn-primary btn-block" style={{ marginTop: 10 }} onClick={closePaymentModal}>Selesai</button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
};

export default CartTable;