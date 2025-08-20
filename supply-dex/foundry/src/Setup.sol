// SPDX-License-Identifier: MIT
pragma solidity ^0.8.29;

import {SuppDexProxy} from "./SuppDexProxy.sol";
import {SuppDexV1} from "./SuppDexV1.sol";

contract Setup {
    SuppDexProxy public TARGET;

    event DeployedTarget(address at);

    constructor() payable {
        SuppDexV1 logic = new SuppDexV1();
        TARGET = new SuppDexProxy(msg.sender, address(logic));
        (bool ok, ) = address(TARGET).call{value: address(this).balance}("");
        require(ok, "funding failed");
        emit DeployedTarget(address(TARGET));
    }

    function isSolved() public view returns (bool) {
        return address(TARGET).balance == 0;
    }
}
