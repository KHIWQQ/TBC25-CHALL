// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

contract SuppDexProxy {
    address public admin;

    bool public initialized;

    address public implementation;

    modifier onlyAdmin() {
        require(msg.sender == admin, "Proxy: not admin");
        _;
    }

    constructor(address _admin, address _implementation) {
        require(_admin != address(0), "Proxy: zero admin");
        require(_implementation.code.length > 0, "Proxy: impl !contract");
        admin = _admin;
        implementation = _implementation;
    }

    function changeAdmin(address newAdmin) external onlyAdmin {
        require(newAdmin != address(0), "Proxy: zero admin");
        admin = newAdmin;
    }

    function upgradeTo(address newImplementation) external onlyAdmin {
        require(newImplementation.code.length > 0, "Proxy: impl !contract");
        implementation = newImplementation;
    }

    fallback() external payable {
        _delegate(implementation);
    }

    receive() external payable {
        _delegate(implementation);
    }

    function _delegate(address _impl) internal {
        assembly {
            calldatacopy(0, 0, calldatasize())
            let result := delegatecall(gas(), _impl, 0, calldatasize(), 0, 0)
            returndatacopy(0, 0, returndatasize())
            switch result
            case 0 {
                revert(0, returndatasize())
            }
            default {
                return(0, returndatasize())
            }
        }
    }
}
