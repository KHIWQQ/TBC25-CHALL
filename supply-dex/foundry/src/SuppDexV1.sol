// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";

/// @dev This version provides ETH custody, operator allowances, and EIP-712 withdrawals.
contract SuppDexV1 {
    address public owner;
    bool public initialized;

    address public guardian;
    address public feeRecipient;
    uint16 public feeBps;
    bool public paused;

    mapping(address => uint256) public balances;
    mapping(address => uint256) public nonces;
    mapping(address => mapping(address => uint256)) public operatorAllowance;

    bytes32 private _DOMAIN_SEPARATOR;
    uint256 private _CACHED_CHAIN_ID;

    event OwnerChanged(address indexed oldOwner, address indexed newOwner);
    event GuardianChanged(
        address indexed oldGuardian,
        address indexed newGuardian
    );
    event FeeConfigChanged(
        uint16 oldFeeBps,
        uint16 newFeeBps,
        address indexed oldRecipient,
        address indexed newRecipient
    );
    event Paused(address indexed by);
    event Unpaused(address indexed by);
    event Deposit(address indexed from, uint256 amount, uint256 fee);
    event Withdraw(address indexed to, uint256 amount, uint256 fee);
    event TransferInternal(
        address indexed from,
        address indexed to,
        uint256 amount
    );
    event OperatorApproval(
        address indexed owner,
        address indexed operator,
        uint256 amount
    );

    error NotOwner();
    error NotGuardian();
    error PausedError();
    error ZeroAddr();
    error FeeTooHigh();
    error InsufficientBalance();
    error AllowanceTooLow();
    error DeadlineExpired();

    modifier onlyOwner() {
        if (msg.sender != owner) revert NotOwner();
        _;
    }

    modifier onlyGuardian() {
        if (msg.sender != guardian) revert NotGuardian();
        _;
    }

    modifier whenNotPaused() {
        if (paused) revert PausedError();
        _;
    }

    function initialize(address _owner) external {
        require(!initialized, "Already initialized");
        initialized = true;
        emit OwnerChanged(owner, _owner);
        owner = _owner;
        _recomputeDomainSeparator();
    }

    function setGuardian(address _guardian) external onlyOwner {
        if (_guardian == address(0)) revert ZeroAddr();
        emit GuardianChanged(guardian, _guardian);
        guardian = _guardian;
    }

    function pause() external onlyGuardian {
        paused = true;
        emit Paused(msg.sender);
    }

    function unpause() external onlyGuardian {
        paused = false;
        emit Unpaused(msg.sender);
    }

    function setFee(uint16 _feeBps, address _feeRecipient) external onlyOwner {
        if (_feeBps > 1000) revert FeeTooHigh();
        if (_feeRecipient == address(0)) revert ZeroAddr();
        emit FeeConfigChanged(feeBps, _feeBps, feeRecipient, _feeRecipient);
        feeBps = _feeBps;
        feeRecipient = _feeRecipient;
    }

    receive() external payable {
        _deposit(msg.sender, msg.value);
    }

    function deposit() external payable whenNotPaused {
        _deposit(msg.sender, msg.value);
    }

    function _deposit(address from, uint256 amount) internal {
        if (amount == 0) return;
        uint256 fee = (amount * feeBps) / 10_000;
        uint256 credited = amount - fee;

        balances[from] += credited;
        emit Deposit(from, amount, fee);
    }

    function withdraw(uint256 amount, address to) external whenNotPaused {
        _withdrawFrom(msg.sender, amount, to);
    }

    function transferInternal(
        address to,
        uint256 amount
    ) external whenNotPaused {
        if (balances[msg.sender] < amount) revert InsufficientBalance();
        balances[msg.sender] -= amount;
        balances[to] += amount;
        emit TransferInternal(msg.sender, to, amount);
    }

    function approveOperator(
        address operator,
        uint256 amount
    ) external whenNotPaused {
        operatorAllowance[msg.sender][operator] = amount;
        emit OperatorApproval(msg.sender, operator, amount);
    }

    function transferFromInternal(
        address from,
        address to,
        uint256 amount
    ) external whenNotPaused {
        uint256 allowed = operatorAllowance[from][msg.sender];
        if (allowed < amount) revert AllowanceTooLow();
        if (balances[from] < amount) revert InsufficientBalance();
        if (allowed != type(uint256).max) {
            operatorAllowance[from][msg.sender] = allowed - amount;
        }
        balances[from] -= amount;
        balances[to] += amount;
        emit TransferInternal(from, to, amount);
    }

    function _withdrawFrom(address from, uint256 amount, address to) internal {
        if (to == address(0)) revert ZeroAddr();
        if (balances[from] < amount) revert InsufficientBalance();

        uint256 fee = (amount * feeBps) / 10_000;
        uint256 payout = amount - fee;
        balances[from] -= amount;

        (bool ok1, ) = to.call{value: payout}("");
        require(ok1, "payout failed");
        if (fee != 0 && feeRecipient != address(0)) {
            (bool ok2, ) = feeRecipient.call{value: fee}("");
            require(ok2, "fee xfer failed");
        }
        emit Withdraw(to, amount, fee);
    }

    // ---- EIP-712 meta-withdraw ----
    // keccak256("Withdraw(address owner,uint256 amount,address to,uint256 nonce,uint256 deadline)")
    bytes32 private constant _WITHDRAW_TYPEHASH =
        0x8f3f593fe2cfb8f7e40f0f61d6efc896b8a6f9b4b5d01ebe2e2d7fe9d6d73e9c;

    function DOMAIN_SEPARATOR() public view returns (bytes32) {
        return
            block.chainid == _CACHED_CHAIN_ID
                ? _DOMAIN_SEPARATOR
                : _calcDomainSeparator();
    }

    function _calcDomainSeparator() internal view returns (bytes32) {
        return
            keccak256(
                abi.encode(
                    // keccak256("EIP712Domain(string name,string version,uint256 chainId,address verifyingContract)")
                    0xd87cd6e0a48b3a0f5b2b9b9f662c1d8c1a35fa9a0b60b9b0a1e0d54d3e5ed2c0,
                    keccak256(bytes("SuppDex")),
                    keccak256(bytes("1")),
                    block.chainid,
                    address(this)
                )
            );
    }

    function _recomputeDomainSeparator() internal {
        _CACHED_CHAIN_ID = block.chainid;
        _DOMAIN_SEPARATOR = _calcDomainSeparator();
    }

    function withdrawWithSig(
        address from,
        uint256 amount,
        address to,
        uint256 deadline,
        uint8 v,
        bytes32 r,
        bytes32 s
    ) external whenNotPaused {
        if (block.timestamp > deadline) revert DeadlineExpired();
        uint256 nonce = nonces[from]++;

        bytes32 digest = keccak256(
            abi.encodePacked(
                "\x19\x01",
                DOMAIN_SEPARATOR(),
                keccak256(
                    abi.encode(
                        _WITHDRAW_TYPEHASH,
                        from,
                        amount,
                        to,
                        nonce,
                        deadline
                    )
                )
            )
        );

        address signer = ecrecover(digest, v, r, s);
        require(signer != address(0) && signer == from, "bad sig");

        _withdrawFrom(from, amount, to);
    }

    function sweepDust(address payable to, uint256 amount) external onlyOwner {
        if (to == address(0)) revert ZeroAddr();
        (bool ok, ) = to.call{value: amount}("");
        require(ok, "sweep failed");
    }

    function upgrade(address newImpl) external onlyOwner {
        require(newImpl.code.length > 0, "impl !contract");
        assembly {
            sstore(1, newImpl)
        }
    }

    function totalAssets() external view returns (uint256) {
        return address(this).balance;
    }

    function version() external pure returns (string memory) {
        return "SuppDexV1";
    }
}
